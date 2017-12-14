<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nette\Utils\Callback;
use Nette\Utils\ObjectMixin;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\NotImplementedException;
use Nextras\Orm\Repository\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Repository\Functions\DisjunctionOperatorFunction;
use Nextras\Orm\Repository\Functions\ValueOperatorFunction;
use ReflectionClass;


abstract class Repository implements IRepository
{
	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforePersist = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterPersist = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforeInsert = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterInsert = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforeUpdate = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterUpdate = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforeRemove = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterRemove = [];

	/** @var array of callbacks with (IEntity[] $persisted, IEntity[] $removed) arguments */
	public $onFlush = [];

	/** @var IMapper */
	protected $mapper;

	/** @var string */
	protected $entityClassName;

	/** @var IModel */
	private $model;

	/** @var IdentityMap */
	private $identityMap;

	/** @var array */
	private $proxyMethods;

	/** @var MetadataStorage */
	private $metadataStorage;

	/** @var array */
	private $entitiesToFlush = [[], []];

	/** @var IDependencyProvider|null */
	private $dependencyProvider;

	/** @var array Collection functions cache */
	private $collectionFunctions = [];


	/**
	 * @param  IMapper             $mapper
	 * @param  IDependencyProvider $dependencyProvider
	 */
	public function __construct(IMapper $mapper, IDependencyProvider $dependencyProvider = null)
	{
		$this->mapper = $mapper;
		$this->mapper->setRepository($this);
		$this->identityMap = new IdentityMap($this);
		$this->dependencyProvider = $dependencyProvider;

		$reflection = new ReflectionClass($this);
		preg_match_all(
			'~^[ \t*]* @method[ \t]+[^\s]+[ \t]+(\w+)\(.*\).*$~um',
			(string) $reflection->getDocComment(), $matches, PREG_SET_ORDER
		);
		foreach ($matches as list(, $methodname)) {
			$this->proxyMethods[strtolower($methodname)] = true;
		}
	}


	public function getModel(): IModel
	{
		if ($this->model === null) {
			throw new InvalidStateException('Repository is not attached to model.');
		}

		return $this->model;
	}


	/** @inheritdoc */
	public function setModel(IModel $model)
	{
		if ($this->model && $this->model !== $model) {
			throw new InvalidStateException('Repository is already attached.');
		}

		$this->model = $model;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	/** @inheritdoc */
	public function getMapper(): IMapper
	{
		if (!$this->mapper) {
			throw new InvalidStateException('Repository does not have injected any mapper.');
		}

		return $this->mapper;
	}


	/** @inheritdoc */
	public function getBy(array $conds)
	{
		return call_user_func_array([$this->findAll(), 'getBy'], func_get_args());
	}


	/** @inheritdoc */
	public function getById($id)
	{
		if ($id === null) {
			return null;
		} elseif ($id instanceof IEntity) {
			$id = $id->getValue('id');
		} elseif (!(is_scalar($id) || is_array($id))) {
			throw new InvalidArgumentException('Primary key value has to be a scalar.');
		}

		$entity = $this->identityMap->getById($id);
		if ($entity === false) { // entity was removed
			return null;
		} elseif ($entity instanceof IEntity) {
			return $entity;
		}

		$entity = $this->findAll()->getBy(['id' => $id]);
		if ($entity === null) {
			$this->identityMap->remove($id);
		}

		return $entity;
	}


	/** @inheritdoc */
	public function findAll(): ICollection
	{
		return $this->getMapper()->findAll();
	}


	/** @inheritdoc */
	public function findBy(array $conds): ICollection
	{
		return call_user_func_array([$this->findAll(), 'findBy'], func_get_args());
	}


	/** @inheritdoc */
	public function findById($ids): ICollection
	{
		return call_user_func_array([$this->findAll(), 'findBy'], [['id' => $ids]]);
	}


	/** @inheritdoc */
	public function getCollectionFunction(string $name)
	{
		if (!isset($this->collectionFunctions[$name])) {
			$this->collectionFunctions[$name] = $this->createCollectionFunction($name);
		}
		return $this->collectionFunctions[$name];
	}


	protected function createCollectionFunction(string $name)
	{
		if ($name === ValueOperatorFunction::class) {
			return new ValueOperatorFunction();
		} elseif ($name === ConjunctionOperatorFunction::class) {
			return new ConjunctionOperatorFunction();
		} elseif ($name === DisjunctionOperatorFunction::class) {
			return new DisjunctionOperatorFunction();
		} else {
			throw new NotImplementedException('Override ' . get_class($this) . '::createCollectionFunction() to return an instance of ' . $name . ' collection function.');
		}
	}


	/** @inheritdoc */
	public function attach(IEntity $entity)
	{
		if (!$entity->isAttached()) {
			$entity->onAttach($this, $this->metadataStorage->get(get_class($entity)));
			if ($this->dependencyProvider) {
				$this->dependencyProvider->injectDependencies($entity);
			}
		}
	}


	/** @inheritdoc */
	public function detach(IEntity $entity)
	{
		if ($entity->isAttached()) {
			$entity->onDetach();
		}
	}


	/** @inheritdoc */
	public function hydrateEntity(array $data)
	{
		return $this->identityMap->create($data);
	}


	/** @inheritdoc */
	public function getEntityMetadata(string $entityClass = null): EntityMetadata
	{
		if ($entityClass !== null && !in_array($entityClass, $this->getEntityClassNames(), true)) {
			throw new InvalidArgumentException("Class '$entityClass' is not accepted by '" . get_class($this) . "' repository.");
		}
		return $this->metadataStorage->get($entityClass ?: static::getEntityClassNames()[0]);
	}


	/** @inheritdoc */
	public function getEntityClassName(array $data): string
	{
		if (!$this->entityClassName) {
			$this->entityClassName = static::getEntityClassNames()[0];
		}

		return $this->entityClassName;
	}


	/** @inheritdoc */
	public function persist(IEntity $entity, bool $withCascade = true)
	{
		$this->identityMap->check($entity);
		return $this->model->persist($entity, $withCascade);
	}


	/** @inheritdoc */
	public function doPersist(IEntity $entity)
	{
		if (!$entity->isModified()) {
			return;
		}

		$isPersisted = $entity->isPersisted();
		if ($isPersisted) {
			$this->onBeforeUpdate($entity);
		} else {
			$this->onBeforeInsert($entity);
		}

		$isPersisted && $this->identityMap->remove($entity->getPersistedId()); // id can change in composite key
		$id = $this->mapper->persist($entity);
		$entity->onPersist($id);
		$this->identityMap->add($entity);
		$this->entitiesToFlush[0][] = $entity;

		if ($isPersisted) {
			$this->onAfterUpdate($entity);
		} else {
			$this->onAfterInsert($entity);
		}
		$this->onAfterPersist($entity);
	}


	/** @inheritdoc */
	public function remove($entity, bool $withCascade = true): IEntity
	{
		$entity = $entity instanceof IEntity ? $entity : $this->getById($entity);
		$this->identityMap->check($entity);
		return $this->model->remove($entity, $withCascade);
	}


	/** @inheritdoc */
	public function doRemove(IEntity $entity)
	{
		$this->detach($entity);
		if (!$entity->isPersisted()) {
			return;
		}

		$this->mapper->remove($entity);
		$this->identityMap->remove($entity->getPersistedId());
		$this->entitiesToFlush[1][] = $entity;
		$this->onAfterRemove($entity);
	}


	/** @inheritdoc */
	public function flush()
	{
		$this->getModel()->flush();
	}


	/** @inheritdoc */
	public function persistAndFlush(IEntity $entity, bool $withCascade = true)
	{
		$this->persist($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	/** @inheritdoc */
	public function removeAndFlush($entity, bool $withCascade = true): IEntity
	{
		$this->remove($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	/** @inheritdoc */
	public function doFlush()
	{
		$this->mapper->flush();
		$this->onFlush($this->entitiesToFlush[0], $this->entitiesToFlush[1]);
		$entities = $this->entitiesToFlush;
		$this->entitiesToFlush = [[], []];
		return $entities;
	}


	/** @inheritdoc */
	public function doClear()
	{
		$this->identityMap->destroyAllEntities();
		$this->mapper->clearCache();
	}


	public function __call($method, $args)
	{
		if (isset($this->proxyMethods[strtolower($method)])) {
			return call_user_func_array([$this->mapper, $method], $args);
		} else {
			return ObjectMixin::call($this, $method, $args);
		}
	}


	public function doRefreshAll(bool $allowOverwrite)
	{
		$ids = [];
		$entities = $this->identityMap->getAll();
		foreach ($entities as $entity) {
			if (!$allowOverwrite && $entity->isModified()) {
				throw new InvalidStateException('Cannot refresh modified entity, flush changes first or set $allowOverwrite flag to true.');
			}
			$this->identityMap->markForRefresh($entity);
			$ids[] = $entity->getPersistedId();
		}
		$this->findById($ids)->fetchAll();
		foreach ($entities as $entity) {
			if (!$this->identityMap->isMarkedForRefresh($entity)) {
				continue;
			}
			$this->detach($entity);
			$this->identityMap->remove($entity->getPersistedId());
			$this->onAfterRemove($entity);
		}
		$this->mapper->clearCache();
	}


	public function onBeforePersist(IEntity $entity)
	{
		$entity->onBeforePersist();
		foreach ($this->onBeforePersist as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onAfterPersist(IEntity $entity)
	{
		$entity->onAfterPersist();
		foreach ($this->onAfterPersist as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onBeforeInsert(IEntity $entity)
	{
		$entity->onBeforeInsert();
		foreach ($this->onBeforeInsert as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onAfterInsert(IEntity $entity)
	{
		$entity->onAfterInsert();
		foreach ($this->onAfterInsert as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onBeforeUpdate(IEntity $entity)
	{
		$entity->onBeforeUpdate();
		foreach ($this->onBeforeUpdate as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onAfterUpdate(IEntity $entity)
	{
		$entity->onAfterUpdate();
		foreach ($this->onAfterUpdate as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onBeforeRemove(IEntity $entity)
	{
		$entity->onBeforeRemove();
		foreach ($this->onBeforeRemove as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onAfterRemove(IEntity $entity)
	{
		$entity->onAfterRemove();
		foreach ($this->onAfterRemove as $handler) {
			Callback::invokeArgs($handler, [$entity]);
		}
	}


	public function onFlush(array $persitedEntities, array $removedEntities)
	{
		foreach ($this->onFlush as $handler) {
			Callback::invokeArgs($handler, [$persitedEntities, $removedEntities]);
		}
	}
}

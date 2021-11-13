<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;


use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\Functions\AvgAggregateFunction;
use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CompareLikeFunction;
use Nextras\Orm\Collection\Functions\CompareNotEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanFunction;
use Nextras\Orm\Collection\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\Functions\DisjunctionOperatorFunction;
use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\Functions\MaxAggregateFunction;
use Nextras\Orm\Collection\Functions\MinAggregateFunction;
use Nextras\Orm\Collection\Functions\SumAggregateFunction;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Exception\NotImplementedException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use ReflectionClass;
use function count;


abstract class Repository implements IRepository
{
	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onBeforePersist = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onAfterPersist = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onBeforeInsert = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onAfterInsert = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onBeforeUpdate = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onAfterUpdate = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onBeforeRemove = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity $entity): void>
	 */
	public $onAfterRemove = [];

	/**
	 * @var callable[]
	 * @phpstan-var list<callable(IEntity[] $persisted, IEntity[] $removed): void>
	 */
	public $onFlush = [];

	/** @var IMapper */
	protected $mapper;

	/**
	 * @var string|null
	 * @phpstan-var class-string<IEntity>|null
	 */
	protected $entityClassName;

	/** @var IModel|null */
	private $model;

	/** @var IdentityMap */
	private $identityMap;

	/** @var array<string, bool> */
	private $proxyMethods;

	/**
	 * @var array
	 * @phpstan-var array{list<IEntity>, list<IEntity>}
	 */
	private $entitiesToFlush = [[], []];

	/** @var IDependencyProvider|null */
	private $dependencyProvider;

	/**
	 * @var object[] Collection functions cache
	 * @phpstan-var array<string, IQueryBuilderFunction|IArrayFunction>
	 */
	private $collectionFunctions = [];

	/**
	 * @var ConditionParser|null
	 */
	private $conditionParser;


	/**
	 * @param IMapper $mapper
	 * @param IDependencyProvider $dependencyProvider
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
		foreach ($matches as [, $methodname]) {
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


	/** {@inheritdoc} */
	public function setModel(IModel $model): void
	{
		if ($this->model !== null && $this->model !== $model) {
			throw new InvalidStateException('Repository is already attached.');
		}

		$this->model = $model;
	}


	/** {@inheritdoc} */
	public function getMapper(): IMapper
	{
		return $this->mapper;
	}


	/** {@inheritdoc} */
	public function getBy(array $conds): ?IEntity
	{
		return $this->findAll()->getBy($conds);
	}


	/** {@inheritDoc} */
	public function getByChecked(array $conds): IEntity
	{
		$entity = $this->getBy($conds);
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	/** {@inheritdoc} */
	public function getById($id): ?IEntity
	{
		if ($id === null) {
			return null;
		} else if ($id instanceof IEntity) { // deprecated
			$id = $id->getValue('id');
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


	/** {@inheritdoc} */
	public function getByIdChecked($id): IEntity
	{
		$entity = $this->getById($id);
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	/** {@inheritdoc} */
	public function findAll(): ICollection
	{
		return $this->mapper->findAll();
	}


	/** {@inheritdoc} */
	public function findBy(array $conds): ICollection
	{
		return $this->findAll()->findBy($conds);
	}


	/**
	 * @param array<mixed>|mixed $ids
	 * @deprecated Use {@see findByIds()}.
	 */
	public function findById($ids): ICollection
	{
		if (!is_array($ids)) {
			return $this->findByIds([$ids]);
		} else {
			return $this->findByIds($ids);
		}
	}


	/** {@inheritdoc} */
	public function findByIds(array $ids): ICollection
	{
		$entities = [];
		$missingEntities = false;

		foreach ($ids as $id) {
			$entity = $this->identityMap->getById($id);
			if ($entity === null || $entity === false) {
				$missingEntities = true;
				break;
			}
			$entities[] = $entity;
		}

		if (!$missingEntities) {
			return new ArrayCollection($entities, $this);
		}

		return $this->findAll()->findBy(['id' => $ids]);
	}


	/** {@inheritdoc} */
	public function getCollectionFunction(string $name)
	{
		if (!isset($this->collectionFunctions[$name])) {
			$this->collectionFunctions[$name] = $this->createCollectionFunction($name);
		}
		return $this->collectionFunctions[$name];
	}


	/**
	 * @return IQueryBuilderFunction|IArrayFunction
	 */
	protected function createCollectionFunction(string $name)
	{
		static $knownFunctions = [
			CompareEqualsFunction::class => true,
			CompareGreaterThanEqualsFunction::class => true,
			CompareGreaterThanFunction::class => true,
			CompareNotEqualsFunction::class => true,
			CompareSmallerThanEqualsFunction::class => true,
			CompareSmallerThanFunction::class => true,
			CompareLikeFunction::class => true,
			AvgAggregateFunction::class => true,
			CountAggregateFunction::class => true,
			MaxAggregateFunction::class => true,
			MinAggregateFunction::class => true,
			SumAggregateFunction::class => true,
		];

		if ($name === ConjunctionOperatorFunction::class) {
			return new ConjunctionOperatorFunction($this->getConditionParser());
		} elseif ($name === DisjunctionOperatorFunction::class) {
			return new DisjunctionOperatorFunction($this->getConditionParser());
		}

		if (isset($knownFunctions[$name])) {
			/** @var IQueryBuilderFunction|IArrayFunction $function */
			$function = new $name();
			return $function;
		} else {
			throw new NotImplementedException('Override ' . get_class($this) . '::createCollectionFunction() to return an instance of ' . $name . ' collection function.');
		}
	}


	/** {@inheritdoc} */
	public function attach(IEntity $entity): void
	{
		if (!$entity->isAttached()) {
			$entity->onAttach($this, MetadataStorage::get(get_class($entity)));
			if ($this->dependencyProvider !== null) {
				$this->dependencyProvider->injectDependencies($entity);
			}
		}
	}


	/** {@inheritdoc} */
	public function detach(IEntity $entity): void
	{
		if ($entity->isAttached()) {
			$entity->onDetach();
		}
	}


	/** {@inheritdoc} */
	public function hydrateEntity(array $data): ?IEntity
	{
		return $this->identityMap->create($data);
	}


	/** {@inheritdoc} */
	public function getEntityMetadata(string $entityClass = null): EntityMetadata
	{
		$classNames = static::getEntityClassNames();
		if ($entityClass !== null && !in_array($entityClass, $classNames, true)) {
			throw new InvalidArgumentException("Class '$entityClass' is not accepted by '" . get_class($this) . "' repository.");
		}
		return MetadataStorage::get($entityClass ?? $classNames[0]);
	}


	/** {@inheritdoc} */
	public function getEntityClassName(array $data): string
	{
		if ($this->entityClassName === null) {
			$this->entityClassName = static::getEntityClassNames()[0];
		}

		return $this->entityClassName;
	}


	/** {@inheritdoc} */
	public function getConditionParser(): ConditionParser
	{
		if ($this->conditionParser === null) {
			$this->conditionParser = new ConditionParser();
		}
		return $this->conditionParser;
	}


	/** {@inheritdoc} */
	public function persist(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->identityMap->check($entity);
		return $this->getModel()->persist($entity, $withCascade);
	}


	/** {@inheritdoc} */
	public function doPersist(IEntity $entity): void
	{
		if (!$entity->isModified()) {
			return;
		}

		$isPersisted = $entity->isPersisted();
		if ($isPersisted) {
			$this->onBeforeUpdate($entity);
			$this->identityMap->remove($entity->getPersistedId()); // id can change in composite key
		} else {
			$this->onBeforeInsert($entity);
		}

		$this->mapper->persist($entity);
		$this->identityMap->add($entity);
		$this->entitiesToFlush[0][] = $entity;

		if ($isPersisted) {
			$this->onAfterUpdate($entity);
		} else {
			$this->onAfterInsert($entity);
		}
		$this->onAfterPersist($entity);
	}


	/** {@inheritdoc} */
	public function remove(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->identityMap->check($entity);
		return $this->getModel()->remove($entity, $withCascade);
	}


	/** {@inheritdoc} */
	public function doRemove(IEntity $entity): void
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


	/** {@inheritdoc} */
	public function flush(): void
	{
		$this->getModel()->flush();
	}


	/** {@inheritdoc} */
	public function persistAndFlush(IEntity $entity, bool $withCascade = true): IEntity
	{
		$return = $this->persist($entity, $withCascade);
		$this->flush();
		return $return;
	}


	/** {@inheritdoc} */
	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity
	{
		$result = $this->remove($entity, $withCascade);
		$this->flush();
		return $result;
	}


	/** {@inheritdoc} */
	public function doFlush(): array
	{
		$this->mapper->flush();
		$this->onFlush($this->entitiesToFlush[0], $this->entitiesToFlush[1]);
		$entities = $this->entitiesToFlush;
		$this->entitiesToFlush = [[], []];
		return $entities;
	}


	/** {@inheritdoc} */
	public function doClear(): void
	{
		$this->identityMap->destroyAllEntities();
		$this->mapper->clearCache();
	}


	/**
	 * @phpstan-param mixed[] $args
	 * @return mixed
	 */
	public function __call(string $method, array $args)
	{
		if (isset($this->proxyMethods[strtolower($method)])) {
			$callback = [$this->mapper, $method];
			assert(is_callable($callback));
			return call_user_func_array($callback, $args);
		} else {
			$class = get_class($this);
			throw new MemberAccessException("Undefined $class::$method() (proxy)method.");
		}
	}


	/** {@inheritdoc} */
	public function doRefreshAll(bool $allowOverwrite): void
	{
		$ids = [];
		$entities = $this->identityMap->getAll();
		foreach ($entities as $entity) {
			if (!$entity->isPersisted()) {
				continue;
			} elseif (!$allowOverwrite && $entity->isModified()) {
				throw new InvalidStateException('Cannot refresh modified entity, flush changes first or set $allowOverwrite flag to true.');
			}
			$this->identityMap->markForRefresh($entity);
			$ids[] = $entity->getPersistedId();
		}
		if (count($ids) > 0) {
			$this->findByIds($ids)->fetchAll();
		}
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


	public function onBeforePersist(IEntity $entity): void
	{
		$entity->onBeforePersist();
		foreach ($this->onBeforePersist as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onAfterPersist(IEntity $entity): void
	{
		$entity->onAfterPersist();
		foreach ($this->onAfterPersist as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onBeforeInsert(IEntity $entity): void
	{
		$entity->onBeforeInsert();
		foreach ($this->onBeforeInsert as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onAfterInsert(IEntity $entity): void
	{
		$entity->onAfterInsert();
		foreach ($this->onAfterInsert as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onBeforeUpdate(IEntity $entity): void
	{
		$entity->onBeforeUpdate();
		foreach ($this->onBeforeUpdate as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onAfterUpdate(IEntity $entity): void
	{
		$entity->onAfterUpdate();
		foreach ($this->onAfterUpdate as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onBeforeRemove(IEntity $entity): void
	{
		$entity->onBeforeRemove();
		foreach ($this->onBeforeRemove as $handler) {
			call_user_func($handler, $entity);
		}
	}


	public function onAfterRemove(IEntity $entity): void
	{
		$entity->onAfterRemove();
		foreach ($this->onAfterRemove as $handler) {
			call_user_func($handler, $entity);
		}
	}


	/**
	 * @param IEntity[] $persitedEntities
	 * @param IEntity[] $removedEntities
	 */
	public function onFlush(array $persitedEntities, array $removedEntities): void
	{
		foreach ($this->onFlush as $handler) {
			call_user_func($handler, $persitedEntities, $removedEntities);
		}
	}
}

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
use Nextras\Orm\Collection\Functions\CollectionFunction;
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
use Nextras\Orm\Collection\Functions\FetchPropertyFunction;
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
use function array_values;
use function count;
use function sort;


/**
 * @template E of IEntity
 * @implements IRepository<E>
 */
abstract class Repository implements IRepository
{
	/** @var array<mixed, callable(E $entity): void> */
	public array $onBeforePersist = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onAfterPersist = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onBeforeInsert = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onAfterInsert = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onBeforeUpdate = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onAfterUpdate = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onBeforeRemove = [];

	/** @var array<mixed, callable(E $entity): void> */
	public array $onAfterRemove = [];

	/** @var array<mixed, callable(E[] $persisted, E[] $removed): void> */
	public array $onFlush = [];

	/** @var class-string<E>|null */
	protected string|null $entityClassName = null;

	private ?IModel $model = null;
	private ConditionParser|null $conditionParser = null;

	/** @var IdentityMap<E> */
	private IdentityMap $identityMap;

	/** @var array<string, bool> */
	private array $proxyMethods = [];

	/** @var array{list<E>, list<E>} */
	private array $entitiesToFlush = [[], []];

	/** @var array<string, CollectionFunction> Collection functions cache */
	private array $collectionFunctions = [];


	/**
	 * @param IMapper<E> $mapper
	 */
	public function __construct(
		protected readonly IMapper $mapper,
		protected readonly IDependencyProvider|null $dependencyProvider = null,
	)
	{
		$this->mapper->setRepository($this);

		/** @var IdentityMap<E> $identityMap */
		$identityMap = new IdentityMap($this);
		$this->identityMap = $identityMap;

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


	public function setModel(IModel $model): void
	{
		if ($this->model !== null && $this->model !== $model) {
			throw new InvalidStateException('Repository is already attached.');
		}

		$this->model = $model;
	}


	public function getMapper(): IMapper
	{
		return $this->mapper;
	}


	public function getBy(array $conds): ?IEntity
	{
		return $this->findAll()->getBy($conds);
	}


	public function getByChecked(array $conds): IEntity
	{
		$entity = $this->getBy($conds);
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	public function getById($id): ?IEntity
	{
		if ($id === null) {
			return null;
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


	public function getByIdChecked($id): IEntity
	{
		$entity = $this->getById($id);
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	public function findAll(): ICollection
	{
		return $this->mapper->findAll();
	}


	public function findBy(array $conds): ICollection
	{
		return $this->findAll()->findBy($conds);
	}


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


	public function getCollectionFunction(string $name): CollectionFunction
	{
		if (!isset($this->collectionFunctions[$name])) {
			$this->collectionFunctions[$name] = $this->createCollectionFunction($name);
		}
		return $this->collectionFunctions[$name];
	}


	protected function createCollectionFunction(string $name): CollectionFunction
	{
		/** @var array<class-string<CollectionFunction>, true> $knownFunctions */
		static $knownFunctions = [
			FetchPropertyFunction::class => true,
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

		if ($name === FetchPropertyFunction::class) {
			return new FetchPropertyFunction($this, $this->mapper, $this->getModel());
		} elseif ($name === ConjunctionOperatorFunction::class) {
			return new ConjunctionOperatorFunction($this->getConditionParser());
		} elseif ($name === DisjunctionOperatorFunction::class) {
			return new DisjunctionOperatorFunction($this->getConditionParser());
		}

		if (isset($knownFunctions[$name])) {
			/** @var CollectionFunction $function */
			$function = new $name();
			return $function;
		} else {
			throw new NotImplementedException('Override ' . get_class($this) . '::createCollectionFunction() to return an instance of ' . $name . ' collection function.');
		}
	}


	public function attach(IEntity $entity): void
	{
		if (!$entity->isAttached()) {
			$entity->onAttach($this, MetadataStorage::get(get_class($entity)));
			if ($this->dependencyProvider !== null) {
				$this->dependencyProvider->injectDependencies($entity);
			}
		}
	}


	public function detach(IEntity $entity): void
	{
		if ($entity->isAttached()) {
			$entity->onDetach();
		}
	}


	public function hydrateEntity(array $data): ?IEntity
	{
		return $this->identityMap->create($data);
	}


	public function getEntityMetadata(string|null $entityClass = null): EntityMetadata
	{
		$classNames = static::getEntityClassNames();
		if ($entityClass !== null && !in_array($entityClass, $classNames, true)) {
			throw new InvalidArgumentException("Class '$entityClass' is not accepted by '" . get_class($this) . "' repository.");
		}
		return MetadataStorage::get($entityClass ?? $classNames[0]);
	}


	public function getEntityClassName(array $data): string
	{
		if ($this->entityClassName === null) {
			/** @var class-string<E> $entityClassName */
			$entityClassName = static::getEntityClassNames()[0];
			$this->entityClassName = $entityClassName;
		}

		return $this->entityClassName;
	}


	public function getConditionParser(): ConditionParser
	{
		if ($this->conditionParser === null) {
			$this->conditionParser = new ConditionParser();
		}
		return $this->conditionParser;
	}


	public function persist(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->identityMap->check($entity);
		$this->getModel()->persist($entity, $withCascade);
		return $entity;
	}


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


	public function remove(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->identityMap->check($entity);
		return $this->getModel()->remove($entity, $withCascade);
	}


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


	public function flush(): void
	{
		$this->getModel()->flush();
	}


	public function persistAndFlush(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->persist($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->remove($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	public function doFlush(): array
	{
		$this->mapper->flush();
		$this->onFlush($this->entitiesToFlush[0], $this->entitiesToFlush[1]);
		$entities = $this->entitiesToFlush;
		$this->entitiesToFlush = [[], []];
		return $entities;
	}


	public function doClear(): void
	{
		$this->identityMap->destroyAllEntities();
		$this->mapper->clearCache();
	}


	/**
	 * @param mixed[] $args
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
			sort($ids); // make ids sorted deterministically
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


	public function onFlush(array $persistedEntities, array $removedEntities): void
	{
		foreach ($this->onFlush as $handler) {
			call_user_func($handler, $persistedEntities, $removedEntities);
		}
	}
}

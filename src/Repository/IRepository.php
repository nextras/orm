<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;


use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;


/**
 * @template E of IEntity
 */
interface IRepository
{
	public function getModel(): IModel;


	public function setModel(IModel $model): void;


	/**
	 * @phpstan-return IMapper<E>
	 */
	public function getMapper(): IMapper;


	/**
	 * Hydrates entity.
	 * @param array<string, mixed> $data
	 * @phpstan-return E|null
	 */
	public function hydrateEntity(array $data): ?IEntity;


	/**
	 * Attaches entity to repository.
	 * @phpstan-param E $entity
	 */
	public function attach(IEntity $entity): void;


	/**
	 * Detaches entity from repository.
	 * @phpstan-param E $entity
	 */
	public function detach(IEntity $entity): void;


	/**
	 * Returns possible entity class names for current repository.
	 * @return string[]
	 * @phpstan-return list<class-string<IEntity>>
	 */
	public static function getEntityClassNames(): array;


	/**
	 * Returns entity metadata.
	 * @param string|null $entityClass for STI (must extends base class)
	 * @phpstan-param class-string<E>|null $entityClass
	 */
	public function getEntityMetadata(string $entityClass = null): EntityMetadata;


	/**
	 * Returns entity class name.
	 * @param array<string, mixed> $data
	 * @phpstan-return class-string<E>
	 */
	public function getEntityClassName(array $data): string;


	/**
	 * Returns IEntity filtered by conditions, null if none found.
	 *
	 * Limits collection via {@see ICollection::findBy()} and returns the first entity (or null).
	 *
	 * @phpstan-param array<string, mixed>|array<mixed> $conds
	 * @phpstan-return E|null
	 */
	public function getBy(array $conds): ?IEntity;


	/**
	 * Returns IEntity filtered by conditions, throw if none found.
	 *
	 * Limits collection via {@see ICollection::findBy()} and returns the first entity (or throw).
	 *
	 * @phpstan-param array<string, mixed>|array<mixed> $conds
	 * @throws NoResultException
	 * @phpstan-return E
	 */
	public function getByChecked(array $conds): IEntity;


	/**
	 * Returns entity by primary value, null if none found.
	 * @param mixed $id
	 * @phpstan-return E|null
	 */
	public function getById($id): ?IEntity;


	/**
	 * Returns entity by primary value, throws if none found.
	 * @param mixed $id
	 * @throws NoResultException
	 */
	public function getByIdChecked($id): IEntity;


	/**
	 * Returns new collection with all entities.
	 * @phpstan-return ICollection<E>
	 */
	public function findAll(): ICollection;


	/**
	 * Returns new collection filtered with conditions.
	 *
	 * There are three types of supported conditions:
	 *
	 * Implicit {@see ICollection::AND} function:
	 * <code>
	 * [
	 *      'property' => 'value
	 * ]
	 * </code>
	 *
	 * Explicit function with inlined arguments:
	 * <code>
	 * [
	 *      ICollection::OR,
	 *      'property1' => 'value',
	 *      'property2' => 'value',
	 * ]
	 * </code>
	 *
	 * Explicit function with non-inlined arguments:
	 * <code>
	 * [
	 *      ICollection::OR,
	 *      ['property' => 'value1'],
	 *      ['property' => 'value2'],
	 * ]
	 * </code>
	 *
	 * @phpstan-param array<string, mixed>|array<int|string, mixed>|list<mixed> $conds
	 * @phpstan-return ICollection<E>
	 */
	public function findBy(array $conds): ICollection;


	/**
	 * Returns entities by primary values.
	 * @param mixed[] $ids
	 * @phpstan-param list<mixed> $ids
	 * @phpstan-return ICollection<E>
	 */
	public function findByIds(array $ids): ICollection;


	/**
	 * Returns collection functions instance.
	 * @return IArrayFunction|IQueryBuilderFunction
	 */
	public function getCollectionFunction(string $name);


	/**
	 * @internal
	 */
	public function getConditionParser(): ConditionParser;


	/**
	 * @template F of E
	 * @phpstan-param F $entity
	 * @phpstan-return F
	 */
	public function persist(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * @template F of E
	 * @phpstan-param F $entity
	 * @phpstan-return F
	 */
	public function persistAndFlush(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * @template F of E
	 * @phpstan-param F $entity
	 * @phpstan-return F
	 */
	public function remove(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * @template F of E
	 * @phpstan-param F $entity
	 * @phpstan-return F
	 */
	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * Flushes all persisted changes in all repositories.
	 */
	public function flush(): void;


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 * @phpstan-param E $entity
	 */
	public function doPersist(IEntity $entity): void;


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 * @phpstan-param E $entity
	 */
	public function doRemove(IEntity $entity): void;


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @phpstan-return array{list<E>, list<E>} array of all persisted & removed entities
	 * @ignore
	 */
	public function doFlush(): array;


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doClear(): void;


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * Fires the event on the entity.
	 * @internal
	 * @ignore
	 */
	public function doRefreshAll(bool $allowOverwrite): void;


	// === events ======================================================================================================


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onBeforePersist(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onAfterPersist(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onBeforeInsert(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onAfterInsert(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onBeforeUpdate(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onAfterUpdate(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onBeforeRemove(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @internal
	 */
	public function onAfterRemove(IEntity $entity): void;
}

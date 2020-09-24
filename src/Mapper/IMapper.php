<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Repository\IRepository;


/**
 * @phpstan-template E of IEntity
 */
interface IMapper
{
	/**
	 * Returns all entities.
	 * @phpstan-return ICollection<E>
	 */
	public function findAll(): ICollection;


	/**
	 * Creates collection with HasOne mapper.
	 * @phpstan-return ICollection<IEntity>
	 */
	public function createCollectionManyHasOne(PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with OneHasOneDirected mapper.
	 * @phpstan-return ICollection<IEntity>
	 */
	public function createCollectionOneHasOne(PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with ManyHasMany mapper.
	 * @phpstan-param IMapper<IEntity> $sourceMapper
	 * @phpstan-return ICollection<IEntity>
	 */
	public function createCollectionManyHasMany(IMapper $sourceMapper, PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with OneHasMany mapper.
	 * @phpstan-return ICollection<IEntity>
	 */
	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection;


	/**
	 * @phpstan-param IRepository<E> $repository
	 */
	public function setRepository(IRepository $repository): void;


	/**
	 * @phpstan-return IRepository<E>
	 */
	public function getRepository(): IRepository;


	/**
	 * Persist entity and return new id.
	 * @phpstan-param E $entity
	 * @internal
	 * @see IRepository::persist()
	 */
	public function persist(IEntity $entity): void;


	/**
	 * @phpstan-param E $entity
	 * @see IRepository::remove()
	 */
	public function remove(IEntity $entity): void;


	/**
	 * @see IRepository::flush()
	 */
	public function flush(): void;


	/**
	 * Clears cache object for collection.
	 * @internal
	 */
	public function clearCache(): void;
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Repository\IRepository;


/**
 * @template E of IEntity
 */
interface IMapper
{
	/**
	 * Returns all entities.
	 * @return ICollection<E>
	 */
	#[\NoDiscard]
	public function findAll(): ICollection;


	/**
	 * Creates collection with HasOne mapper.
	 * @return ICollection<E>
	 */
	#[\NoDiscard]
	public function createCollectionManyHasOne(PropertyMetadata $metadata): ICollection;


	/**
	 * Creates a collection with OneHasOneDirected mapper.
	 * @return ICollection<E>
	 */
	#[\NoDiscard]
	public function createCollectionOneHasOne(PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with ManyHasMany mapper.
	 * @param IMapper<IEntity> $sourceMapper
	 * @return ICollection<E>
	 */
	#[\NoDiscard]
	public function createCollectionManyHasMany(IMapper $sourceMapper, PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with OneHasMany mapper.
	 * @return ICollection<E>
	 */
	#[\NoDiscard]
	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection;


	/**
	 * @param IRepository<E> $repository
	 */
	public function setRepository(IRepository $repository): void;


	/**
	 * @return IRepository<E>
	 */
	#[\NoDiscard]
	public function getRepository(): IRepository;


	/**
	 * Persist entity and return new id.
	 * @param E $entity
	 * @internal
	 * @see IRepository::persist()
	 */
	public function persist(IEntity $entity): void;


	/**
	 * @param E $entity
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

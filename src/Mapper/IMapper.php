<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Repository\IRepository;


interface IMapper
{
	/**
	 * Returns all entities.
	 */
	public function findAll(): ICollection;


	/**
	 * Creates collection with HasOne mapper.
	 */
	public function createCollectionManyHasOne(PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with OneHasOneDirected mapper.
	 */
	public function createCollectionOneHasOne(PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with ManyHasMany mapper.
	 */
	public function createCollectionManyHasMany(IMapper $sourceMapper, PropertyMetadata $metadata): ICollection;


	/**
	 * Creates collection with OneHasMany mapper.
	 */
	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection;


	public function setRepository(IRepository $repository): void;


	public function getRepository(): IRepository;


	/**
	 * @internal
	 * @see IRepository::persist()
	 */
	public function persist(IEntity $entity): void;


	/**
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

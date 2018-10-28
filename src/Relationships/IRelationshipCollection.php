<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Countable;
use IteratorAggregate;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyInjection;


interface IRelationshipCollection extends IPropertyInjection, IEntityAwareProperty, IteratorAggregate, Countable
{
	/**
	 * Adds entity.
	 * @param  IEntity|string|int $entity
	 */
	public function add($entity): ?IEntity;


	/**
	 * Replaces all entities with given ones.
	 * @param  IEntity[]|string[]|int[] $data
	 */
	public function set(array $data): IRelationshipCollection;


	/**
	 * Removes entity.
	 * @param  IEntity|string|int $entity
	 */
	public function remove($entity): ?IEntity;


	/**
	 * @param  IEntity|string|int $entity
	 */
	public function has($entity): bool;


	/**
	 * Returns collection of all entity.
	 */
	public function get(): ICollection;


	/**
	 * Returns true if colletion was loaded.
	 */
	public function isLoaded(): bool;


	/**
	 * Returns true if relationship is modified.
	 */
	public function isModified(): bool;


	/**
	 * Counts collection entities without fetching them from storage.
	 */
	public function countStored(): int;


	/**
	 * @internal
	 * @ignore
	 */
	public function trackEntity(IEntity $entity): void;


	/**
	 * Returns IEntity or IRelationshipContainer for persistence.
	 * @internal
	 * @ignore
	 * @return mixed[]
	 */
	public function getEntitiesForPersistence();


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doPersist();
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Nextras\Orm\Entity\IEntity;


interface IRelationshipMapperManyHasMany extends IRelationshipMapper
{
	/**
	 * Adds entity relationships with passed ids.
	 * @param list<mixed> $addIds array of ids (part of composite PK) to be inserted
	 */
	public function add(IEntity $parent, array $addIds): void;


	/**
	 * Removes entity relationships with passed ids.
	 * @param list<mixed> $removeIds array of ids (part of composite PK) to be removed
	 */
	public function remove(IEntity $parent, array $removeIds): void;
}

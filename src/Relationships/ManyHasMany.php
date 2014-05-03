<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\Collection\ArrayCollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;


class ManyHasMany extends HasMany implements IRelationshipCollection
{
	/** @var IMapper */
	protected $mapperOne;

	/** @var IMapper */
	protected $mapperTwo;


	public function __construct(IEntity $parent, PropertyMetadata $metadata)
	{
		parent::__construct($parent, $metadata);

		if ($metadata->args[2]) { // primary
			$this->mapperOne = $this->parent->getRepository()->getMapper();
			$this->mapperTwo = $this->targetRepository->getMapper();
		} else {
			$this->mapperOne = $this->targetRepository->getMapper();
			$this->mapperTwo = $this->parent->getRepository()->getMapper();
		}
	}


	public function persist($recursive = TRUE)
	{
		$toRemove = $toAdd = [];

		foreach ((array) $this->toRemove as $entity) {
			if (isset($entity->id)) {
				$toRemove[$entity->id] = $entity->id;
			}
		}

		if ($this->collection) {
			foreach ($this->collection as $entity) {
				if ($recursive || !isset($entity->id)) {
					$this->targetRepository->persist($entity, $recursive);
				}
			}
		}

		foreach ((array) $this->toAdd as $entity) {
			if ($recursive || !isset($entity->id)) {
				$this->targetRepository->persist($entity, $recursive);
			}
			$toAdd[$entity->id] = $entity->id;
		}

		if ($this->metadata->args[2]) {
			if ($toRemove) {
				$this->getMapper()->remove($toRemove);
			}
			if ($toAdd) {
				$this->getMapper()->add($toAdd);
			}
		}

		$this->toRemove = $this->toAdd = [];
		if ($this->collection instanceof ArrayCollection) {
			$this->collection = NULL;
		}
	}


	protected function createCollection()
	{
		return $this->mapperOne->createCollectionManyHasMany($this->mapperTwo, $this->metadata, $this->parent);
	}


	protected function getMapper()
	{
		return $this->mapperOne->getCollectionMapperManyHasMany($this->mapperTwo, $this->metadata);
	}

}

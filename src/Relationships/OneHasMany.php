<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotImplementedException;


class OneHasMany extends HasMany implements IRelationshipCollection
{
	/** @var IMapper */
	protected $targetMapper;


	public function __construct(IEntity $parent, PropertyMetadata $metadata)
	{
		parent::__construct($parent, $metadata);
		$this->targetMapper = $this->targetRepository->getMapper();
	}


	public function persist($recursive = TRUE)
	{
		// relations are stored in entites
		// todo: persist entites when method is called directly
	}


	protected function createCollection()
	{
		return $this->targetMapper->createCollectionOneHasMany($this->targetMapper, $this->metadata, $this->parent);
	}

}

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
		throw new NotImplementedException();
	}


	protected function createCollection()
	{
		return $this->targetMapper->createCollectionOneHasMany($this->targetMapper, $this->metadata, $this->parent);
	}


	protected function getMapper()
	{
		return $this->targetMapper->getCollectionMapperOneHasMany($this->targetMapper, $this->metadata);
	}

}

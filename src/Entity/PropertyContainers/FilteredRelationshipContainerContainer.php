<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\PropertyContainers;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\InvalidStateException;


class FilteredRelationshipContainerContainer implements IPropertyContainer
{
	/** @var IRelationshipContainer */
	protected $container;

	/** @var PropertyMetadata */
	protected $metadata;


	public function __construct(IEntity $parent, PropertyMetadata $metadata)
	{
		$this->container = $parent->getProperty($metadata->args[0]);
		$this->metadata = $metadata;
	}


	public function getInjectedValue()
	{
		return $this->container->getEntity($this->metadata->name);
	}


	public function setInjectedValue($value)
	{
		throw new InvalidStateException('FilteredRelationshipCollectionContainer is read-only.');
	}

}

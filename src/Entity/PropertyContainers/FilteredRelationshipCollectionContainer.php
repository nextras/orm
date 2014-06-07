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
use Nextras\Orm\Entity\IPropertyInjection;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\InvalidStateException;


class FilteredRelationshipCollectionContainer implements IPropertyInjection, \IteratorAggregate, \Countable
{
	/** @var IRelationshipCollection */
	protected $relationship;

	/** @var PropertyMetadata */
	protected $metadata;


	public function __construct(IEntity $parent, PropertyMetadata $metadata)
	{
		$this->relationship = $parent->{$metadata->args[0]};
		$this->metadata = $metadata;
	}


	public function getIterator()
	{
		return $this->relationship->getIterator($this->metadata->name);
	}


	public function count()
	{
		return $this->relationship->count($this->metadata->name);
	}


	public function setInjectedValue($value)
	{
		throw new InvalidStateException('FilteredRelationshipCollectionContainer is read-only.');
	}


	public function getInjectedValue()
	{
		throw new NotSupportedException();
	}


	public function getStorableValue()
	{
		throw new NotSupportedException();
	}

}

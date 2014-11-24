<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\PropertyContainers;

use Nette\Utils\DateTime;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\NullValueException;


class DateTimePropertyContainer implements IPropertyContainer
{
	/** @var DateTime */
	private $value;

	/** @var bool */
	private $isModified = FALSE;

	/** @var IEntity */
	private $entity;

	/** @var PropertyMetadata */
	private $metadata;


	public function __construct(IEntity $entity, PropertyMetadata $metadata, $value)
	{
		$this->entity = $entity;
		$this->metadata = $metadata;
		$this->setInjectedValue($value);
	}


	public function setInjectedValue($value)
	{
		if ($value === NULL) {
			if (!$this->metadata->isNullable) {
				throw new NullValueException($this->entity, $this->metadata);
			}

			$this->isModified = $this->value !== NULL;
			$this->value = NULL;

		} else {
			$old = $this->value;
			$this->value = DateTime::from($value);
			$this->isModified = $old == $value; // intentionally ==
		}
	}


	public function getInjectedValue($allowNull = FALSE)
	{
		if ($this->value === NULL && !$this->metadata->isNullable && !$allowNull) {
			throw new NullValueException($this->entity, $this->metadata);
		}

		return $this->value;
	}


	public function getRawValue()
	{
		return (string) $this->value;
	}


	public function isModified()
	{
		return $this->isModified;
	}

}

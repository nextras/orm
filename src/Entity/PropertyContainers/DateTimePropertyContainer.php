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
use Nextras\Orm\InvalidArgumentException;


class DateTimePropertyContainer implements IPropertyContainer
{
	/** @var DateTime */
	private $value;

	/** @var bool */
	private $isNullable;

	/** @var bool */
	private $isModified = FALSE;


	public function __construct(IEntity $entity, PropertyMetadata $metadata, $value)
	{
		$this->isNullable = $metadata->isNullable;
		if ($value) {
			$this->setInjectedValue($value);
		}
	}


	public function setInjectedValue($value)
	{
		if ($value === NULL) {
			if (!$this->isNullable) {
				throw new InvalidArgumentException('DateTime value cannot be a NULL.');
			} else {
				$this->isModified = $this->value !== NULL;
				$this->value = NULL;
			}
		} else {
			$old = $this->value;
			$this->value = DateTime::from($value);
			$this->isModified = $old == $value; // intentionally ==
		}
	}


	public function getInjectedValue()
	{
		return $this->value;
	}


	public function isModified()
	{
		return $this->isModified;
	}

}

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
				$this->value = NULL;
			}
		} else {
			$this->value = DateTime::from($value);
		}
	}


	public function getInjectedValue()
	{
		return $this->value;
	}

}

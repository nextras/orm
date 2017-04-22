<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Embeddable;

use Nextras\Orm\Entity\ImmutableDataTrait;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\NotSupportedException;


class Embeddable implements IEmbeddable
{
	use ImmutableDataTrait;


	public function __construct(array $data)
	{
		$this->metadata = $this->createMetadata();
		if (count($data) !== count($this->metadata->getProperties())) {
			$n = count($data);
			$total = count($this->metadata->getProperties());
			throw new InvalidArgumentException("Only $n of $total was set. Construct embeddable with all its properties. ");
		}
		$this->setImmutableData($data);
	}


	public function onLoad(array $data)
	{
		$this->metadata = $this->createMetadata();
		$this->data = $data;
		foreach ($this->metadata->getProperties() as $name => $metadataProperty) {
			if (!isset($data[$name])) {
				$this->data[$name] = null;
			}
		}
	}


	public function __get($name)
	{
		return $this->getValue($name);
	}


	public function __isset($name)
	{
		if (!$this->metadata->hasProperty($name)) {
			return false;
		}
		return $this->hasValue($name);
	}


	public function __set($name, $value)
	{
		throw new NotSupportedException();
	}


	public function __unset($name)
	{
		throw new NotSupportedException();
	}


	private function initProperty(PropertyMetadata $metadata, string $name)
	{
		$this->validated[$name] = true;

		if (!isset($this->data[$name])) {
			$this->data[$name] = null;
		}

		if ($metadata->container) {
			$property = $this->createPropertyContainer($metadata);
			$property->loadValue($this->data);
			$this->data[$name] = $property;

		} elseif ($this->data[$name] !== null) {
			// data type coercion
			$this->validate($metadata, $name, $this->data[$name]);
		}
	}
}

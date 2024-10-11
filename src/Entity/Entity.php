<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Collection\IEntityPreloadContainer;
use Nextras\Orm\Exception\NotSupportedException;


class Entity extends AbstractEntity implements IEntityHasPreloadContainer
{
	private IEntityPreloadContainer|null $preloadContainer = null;


	/**
	 * @return mixed
	 */
	public function &__get(string $name)
	{
		$var = $this->getValue($name);
		return $var;
	}


	/**
	 * @param mixed $value
	 */
	public function __set(string $name, $value): void
	{
		$this->setValue($name, $value);
	}


	public function __isset(string $name): bool
	{
		if (!$this->metadata->hasProperty($name)) {
			return false;
		}
		return $this->hasValue($name);
	}


	public function __unset(string $name)
	{
		throw new NotSupportedException();
	}


	public function __clone()
	{
		parent::__clone();
		$this->preloadContainer = null;
	}


	public function setPreloadContainer(?IEntityPreloadContainer $overIterator): void
	{
		$this->preloadContainer = $overIterator;
	}


	public function getPreloadContainer(): ?IEntityPreloadContainer
	{
		return $this->preloadContainer;
	}
}

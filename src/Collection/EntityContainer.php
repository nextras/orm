<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Nextras\Orm\Entity\IEntityHasPreloadContainer;


class EntityContainer implements IEntityContainer, IEntityPreloadContainer
{
	/** @var array */
	private $data;

	/** @var array */
	private $preloadCache;


	public function __construct(array $data)
	{
		$this->data = $data;
	}


	public function getEntity($key)
	{
		if (!isset($this->data[$key])) {
			return null;
		}

		$current = $this->data[$key];
		if ($current instanceof IEntityHasPreloadContainer) {
			$current->setPreloadContainer($this);
		}
		return $current;
	}


	public function getPreloadValues(string $property): array
	{
		if (isset($this->preloadCache[$property])) {
			return $this->preloadCache[$property];
		}

		$values = [];
		foreach ($this->data as $entity) {
			$values[] = $entity->getRawValue($property);
		}
		return $this->preloadCache[$property] = $values;
	}
}

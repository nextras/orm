<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Collection\IEntityPreloadContainer;


interface IEntityHasPreloadContainer
{
	/**
	 * Sets the collection of entities for the loading relations at once.
	 */
	public function setPreloadContainer(?IEntityPreloadContainer $overIterator): void;


	public function getPreloadContainer(): ?IEntityPreloadContainer;
}

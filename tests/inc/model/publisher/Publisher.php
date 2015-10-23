<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Relationships\OneHasMany as OHM;
use Nextras\Orm\Repository\IRepository;


/**
 * @property string            $name
 * @property OHM|Book[]        $books    {1:m Book::$publisher}
 * @property LocationStruct    $location {container JsonProxy}
 */
final class Publisher extends Entity
{

	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		parent::onAttach($repository, $metadata);

		$this->location; // trigger ORM magic
	}

}

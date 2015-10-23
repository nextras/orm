<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property string            $name
 * @property OHM|Book[]        $books    {1:m Book::$publisher}
 * @property LocationStruct    $location {container JsonProxy}
 */
final class Publisher extends Entity
{

	protected function onAttachWithDataGuarantee()
	{
		$this->location; // trigger ORM magic
	}

}

<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany;


/**
 * @property string $name
 * @property ManyHasMany|Book[] $books {m:n BooksRepository}
 */
final class Tag extends Entity
{

	public function __construct($name = NULL)
	{
		parent::__construct();
		if ($name) {
			$this->name = $name;
		}
	}

}

<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Model;

use Nextras\Orm\Repository\Repository;


final class AuthorsRepository extends Repository
{

	public function findByTags($name)
	{
		return $this->findBy(['this->books->tags.name' => $name])->groupBy('id');
	}

}

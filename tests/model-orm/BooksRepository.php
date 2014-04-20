<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Model;

use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


final class BooksRepository extends Repository
{

	public function findLatest()
	{
		return $this->findAll()
			->orderBy('id', ICollection::DESC)
			->limit(3);
	}


	public function findByTags($name)
	{
		return $this->findBy(['this->tags.name' => $name]);
	}

}

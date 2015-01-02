<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @method Book getByTitle(string $title)
 * @method ICollection|Book[] findBooksWithEvenId()
 */
final class BooksRepository extends Repository
{

	public function findLatest()
	{
		return $this->findAll()
			->orderBy('id', ICollection::DESC)
			->limitBy(3);
	}


	public function findByTags($name)
	{
		return $this->findBy(['this->tags->name' => $name]);
	}

}

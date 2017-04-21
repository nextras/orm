<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @method Book|NULL getById($id)
 * @method ICollection|Book[] findBooksWithEvenId()
 */
final class BooksRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Book::class];
	}


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

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @method ICollection<Book>|Book[] findBooksWithEvenId()
 * @method Book|null findFirstBook()
 * @extends Repository<Book>
 */
final class BooksRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Book::class];
	}


	/** @return Book[]|ICollection<Book> */
	public function findLatest(): ICollection
	{
		return $this->findAll()
			->orderBy('id', ICollection::DESC)
			->limitBy(3);
	}


	/** @return Book[]|ICollection<Book> */
	public function findByTags(string $name): ICollection
	{
		return $this->findBy(['tags->name' => $name]);
	}
}

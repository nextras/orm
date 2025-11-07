<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Collection\Functions\CollectionFunction;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @method ICollection<Book> findBooksWithEvenId()
 * @method Book|null findFirstBook()
 * @extends Repository<Book>
 */
final class BooksRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Book::class];
	}


	public function getCollectionFunction(string $name): CollectionFunction
	{
		if ($name === TestingPrefixFunction::class) {
			return new TestingPrefixFunction();
		}
		return parent::getCollectionFunction($name);
	}


	/** @return ICollection<Book> */
	public function findLatest(): ICollection
	{
		return $this->findAll()
			->orderBy('id', ICollection::DESC)
			->limitBy(3);
	}


	/** @return ICollection<Book> */
	public function findByTags(string $name): ICollection
	{
		return $this->findBy(['tags->name' => $name]);
	}
}

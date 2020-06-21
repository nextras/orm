<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Collection;


use DateTime;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class ArrayCollectionTest extends TestCase
{

	public function testPassingScalarArray(): void
	{
		$collection = new ArrayCollection([
			$this->e(Author::class, ['id' => 1]),
			$this->e(Author::class, ['id' => 2]),
		], $this->orm->authors);

		$iterator = $collection->getIterator();
		Assert::true($iterator->valid());
	}


	public function testPassingNonList(): void
	{
		Assert::throws(function () {
			new ArrayCollection([
				1 => $this->e(Author::class),
			], $this->orm->authors);
		}, InvalidArgumentException::class);
	}


	public function testFiltering(): void
	{
		/** @var ICollection $collection */
		[$collection, $authors, $books] = $this->createCollection();

		Assert::same($authors, iterator_to_array($collection));

		Assert::same([$authors[1]], iterator_to_array($collection->findBy(['name' => 'Sansa'])));
		Assert::same([$authors[1]], iterator_to_array($collection->findBy(['books->title' => 'Valyria 2'])));
		Assert::same([$authors[0]], iterator_to_array($collection->findBy(['books->title' => 'Valyria 1'])));
		Assert::same([$authors[0]], iterator_to_array($collection->findBy(['books->title' => 'The Wall'])));

		// IN operator
		Assert::same(
			[$authors[0], $authors[1]],
			iterator_to_array($collection->findBy(['books->title' => ['The Wall', 'Valyria 2']]))
		);
	}


	public function testFilteringDatetime(): void
	{
		/** @var ICollection $collection */
		[$collection, $authors, $books] = $this->createCollection();

		Assert::same(1, $collection->findBy(['born<' => new DateTime('2011-01-02')])->count());
		Assert::same(1, $collection->findBy(['born<' => '2011-01-02'])->count());
	}


	public function testFilteringEntity(): void
	{
		$author = $this->e(Author::class, ['id' => 1111, 'title' => 'Nextras Orm']);
		$collection = new ArrayCollection([
			$this->e(Book::class, ['author' => $author]),
			$this->e(Book::class, ['author' => $author]),
			$this->e(Book::class),
		], $this->orm->books);

		$collection = $collection->findBy(['author' => 1111]);
		Assert::same(2, $collection->count());
	}


	public function testSorting(): void
	{
		/** @var ICollection $collection */
		[$collection, $authors, $books] = $this->createCollection();

		Assert::same(
			[$authors[2], $authors[0], $authors[1]],
			iterator_to_array($collection->orderBy('name'))
		);
		Assert::same(
			[$authors[1], $authors[0], $authors[2]],
			iterator_to_array($collection->orderBy('name', ICollection::DESC))
		);
		Assert::same(
			[$authors[0], $authors[2], $authors[1]],
			iterator_to_array($collection->orderBy('born', ICollection::DESC))
		);
		Assert::same(
			[$authors[2], $authors[1], $authors[0]],
			iterator_to_array($collection->orderBy('age', ICollection::DESC)->orderBy('name'))
		);
		Assert::same(
			[$authors[2], $authors[1], $authors[0]],
			iterator_to_array($collection->orderBy(['age' => ICollection::DESC, 'name' => ICollection::ASC]))
		);
		Assert::same(
			[$authors[1], $authors[2], $authors[0]],
			iterator_to_array($collection->orderBy('age', ICollection::DESC)->orderBy('name', ICollection::DESC))
		);
		Assert::same(
			[$authors[1], $authors[2], $authors[0]],
			iterator_to_array($collection->orderBy(['age' => ICollection::DESC, 'name' => ICollection::DESC]))
		);
	}


	public function testSortingWithNull(): void
	{
		$books = [
			$this->e(Book::class, ['title' => 'a', 'printedAt' => null]),
			$this->e(Book::class, ['title' => 'b', 'printedAt' => new DateTime('2018-01-01 10:00:00')]),
			$this->e(Book::class, ['title' => 'c', 'printedAt' => null]),
			$this->e(Book::class, ['title' => 'd', 'printedAt' => new DateTime('2017-01-01 10:00:00')]),
		];

		/** @var Book[]|ArrayCollection */
		$collection = new ArrayCollection($books, $this->orm->books);
		$collection = $collection->orderBy('printedAt', ICollection::DESC_NULLS_LAST);

		$datetimes = [];
		foreach ($collection as $book) {
			$datetimes[] = $book->title;
		}

		Assert::same(['b', 'd', 'a', 'c'], $datetimes);
	}


	public function testSlicing(): void
	{
		/** @var ICollection $collection */
		[$collection, $authors, $books] = $this->createCollection();

		Assert::same($authors, iterator_to_array($collection->limitBy(3)));
		Assert::same([$authors[0]], iterator_to_array($collection->limitBy(1)));
		Assert::same([$authors[1]], iterator_to_array($collection->limitBy(1, 1)));
		Assert::same([$authors[1], $authors[2]], iterator_to_array($collection->limitBy(2, 1)));
		Assert::same([], iterator_to_array($collection->limitBy(2, 3)));
	}


	public function testTogether(): void
	{
		/** @var ICollection $collection */
		[$collection, $authors, $books] = $this->createCollection();

		Assert::same(
			[$authors[0]],
			iterator_to_array($collection
				->findBy(['books->title' => ['Valyria 1', 'Valyria 2']])
				->orderBy('age')
				->limitBy(1))
		);

		Assert::same(
			[$authors[1]],
			iterator_to_array($collection
				->findBy(['books->title' => ['Valyria 1', 'Valyria 2']])
				->orderBy('age')
				->limitBy(2, 1))
		);
	}


	public function testCount(): void
	{
		/** @var ICollection $collection */
		[$collection, $authors, $books] = $this->createCollection();

		Assert::same(
			1,
			count($collection
				->findBy(['books->title' => ['Valyria 1', 'Valyria 2']])
				->orderBy('age')
				->limitBy(2, 1))
		);
	}


	public function testOperators(): void
	{
		$books = new ArrayCollection([
			$this->e(Book::class, ['title' => '1']),
			$this->e(Book::class, ['title' => '2']),
			$this->e(Book::class, ['title' => '3']),
			$this->e(Book::class, ['title' => '4']),
		], $this->orm->books);

		Assert::equal(2, $books->findBy(['title>=' => 3])->count());
		Assert::equal(3, $books->findBy(['title<=' => 3])->count());
		Assert::equal(1, $books->findBy(['title>' => 3])->count());
		Assert::equal(2, $books->findBy(['title<' => 3])->count());
	}


	/**
	 * @return array{ICollection|Author[], Author[], Book[]}
	 */
	private function createCollection(): array
	{
		$authors = [
			$this->e(Author::class, ['name' => 'Jon', 'born' => '2012-01-01']),
			$this->e(Author::class, ['name' => 'Sansa', 'born' => '2011-01-01']),
			$this->e(Author::class, ['name' => 'Eddard', 'born' => '2011-06-01']),
		];

		$books = [
			$this->e(Book::class, ['title' => 'The Wall', 'author' => $authors[0]]),
			$this->e(Book::class, ['title' => 'Valyria 1', 'author' => $authors[0]]),
			$this->e(Book::class, ['title' => 'Valyria 2', 'author' => $authors[1]]),
			$this->e(Book::class, ['title' => 'Valyria 3', 'author' => $authors[2]]),
		];

		/** @var ICollection|Author[] $collection */
		$collection = new ArrayCollection($authors, $this->orm->authors);
		return [$collection, $authors, $books];
	}
}


$test = new ArrayCollectionTest($dic);
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\EmptyCollection;
use Nextras\Orm\Collection\HasManyCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Exception\NoResultException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionTest extends DataTestCase
{
	public function testCountOnOrdered(): void
	{
		$collection = $this->orm->books->findAll();
		$collection = $collection->orderBy('id');
		Assert::same(4, $collection->countStored());
	}


	public function testCountInCycle(): void
	{
		$ids = [];
		$books = $this->orm->authors->getByIdChecked(1)->books;
		foreach ($books as $book) {
			$ids[] = $book->id;
			Assert::equal(2, $books->count());
		}
		sort($ids);
		Assert::equal([1, 2], $ids);
	}


	public function testCountOnLimited(): void
	{
		$collection = $this->orm->books->findAll();
		$collection = $collection->orderBy('id')->limitBy(1, 1);
		Assert::same(1, $collection->count());

		$collection = $collection->limitBy(1, 10);
		Assert::same(0, $collection->count());

		$collection = $this->orm->books->findAll();
		$collection = $collection->orderBy('id')->limitBy(1, 1);
		Assert::same(1, $collection->countStored());

		$collection = $collection->limitBy(1, 10);
		Assert::same(0, $collection->countStored());
	}


	public function testCountOnLimitedWithJoin(): void
	{
		$collection = $this->orm->books->findBy(['author->name' => 'Writer 1'])->orderBy('id')->limitBy(5);
		Assert::same(2, $collection->countStored());

		$collection = $this->orm->tagFollowers->findBy(['tag->name' => 'Tag 1'])->orderBy('tag')->limitBy(3);
		Assert::same(1, $collection->countStored());
	}


	public function testQueryByEntity(): void
	{
		$author1 = $this->orm->authors->getByIdChecked(1);
		$books = $this->orm->books->findBy(['author' => $author1]);
		Assert::same(2, $books->countStored());
		Assert::same(2, $books->count());

		$author2 = $this->orm->authors->getByIdChecked(2);
		$books = $this->orm->books->findBy(['author' => [$author1, $author2]]);
		Assert::same(4, $books->countStored());
		Assert::same(4, $books->count());
	}


	public function testOrdering(): void
	{
		$ids = $this->orm->books->findAll()
			->orderBy('author->id', ICollection::DESC)
			->orderBy('title', ICollection::ASC)
			->fetchPairs(null, 'id');
		Assert::same([3, 4, 1, 2], $ids);

		$ids = $this->orm->books->findAll()
			->orderBy('author->id', ICollection::DESC)
			->orderBy('title', ICollection::DESC)
			->fetchPairs(null, 'id');
		Assert::same([4, 3, 2, 1], $ids);
	}


	public function testOrderingMultiple(): void
	{
		$ids = $this->orm->books->findAll()
			->orderBy([
				'author->id' => ICollection::DESC,
				'title' => ICollection::ASC,
			])
			->fetchPairs(null, 'id');
		Assert::same([3, 4, 1, 2], $ids);

		$ids = $this->orm->books->findAll()
			->orderBy([
				'author->id' => ICollection::DESC,
				'title' => ICollection::DESC,
			])
			->fetchPairs(null, 'id');
		Assert::same([4, 3, 2, 1], $ids);
	}


	public function testOrderingWithOptionalProperty(): void
	{
		$bookIds = $this->orm->books->findAll()
			->orderBy('translator->name', ICollection::ASC_NULLS_FIRST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([2, 1, 3, 4], $bookIds);

		$bookIds = $this->orm->books->findAll()
			->orderBy('translator->name', ICollection::DESC_NULLS_FIRST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([2, 3, 4, 1], $bookIds);

		$bookIds = $this->orm->books->findAll()
			->orderBy('translator->name', ICollection::ASC_NULLS_LAST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1, 3, 4, 2], $bookIds);

		$bookIds = $this->orm->books->findAll()
			->orderBy('translator->name', ICollection::DESC_NULLS_LAST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([3, 4, 1, 2], $bookIds);
	}


	public function testOrderingDateTimeImmutable(): void
	{
		$books = $this->orm->books->findAll()
			->orderBy('publishedAt', ICollection::DESC);

		$ids = [];
		foreach ($books as $book) {
			$ids[] = $book->id;
		}

		Assert::same([1, 3, 2, 4], $ids);
	}


	public function testEmptyArray(): void
	{
		$books = $this->orm->books->findBy(['id' => []]);
		Assert::same(0, $books->count());

		$books = $this->orm->books->findBy(['id!=' => []]);
		Assert::same(4, $books->count());
	}


	public function testConditionsInSameJoin(): void
	{
		$books = $this->orm->books->findBy([
			'author->name' => 'Writer 1',
			'author->web' => 'http://example.com/1',
		]);

		Assert::same(2, $books->count());
	}


	public function testConditionsInDifferentJoinsAndSameTable(): void
	{
		$book = new Book();
		$book->title = 'Books 5';
		$book->author = 1;
		$book->translator = 2;
		$book->publisher = 1;
		$this->orm->books->persistAndFlush($book);

		$books = $this->orm->books->findBy([
			'author->name' => 'Writer 1',
			'translator->web' => 'http://example.com/2',
		]);

		Assert::same(1, $books->count());
	}


	public function testJoinDifferentPath(): void
	{
		$book3 = $this->orm->books->getByIdChecked(3);

		$book3->ean = new Ean();
		assert($book3->ean !== null); // why PHPStan fails here?
		$book3->ean->code = '123';
		$this->orm->persistAndFlush($book3);

		$book5 = new Book();
		$book5->title = 'Book 5';
		$book5->author = 1;
		$book5->publisher = 1;
		$book5->nextPart = 4;
		$book5->ean = new Ean();
		assert($book5->ean !== null); // why PHPStan fails here?
		$book5->ean->code = '456';
		$this->orm->persistAndFlush($book5);

		$book4 = $this->orm->books->getByIdChecked(4);

		$books = $this->orm->books->findBy([
			'nextPart->ean->code' => '123',
			'previousPart->ean->code' => '456',
		]);

		Assert::count(1, $books);

		Assert::same($book4, $books->fetch());
	}


	public function testCompositePK(): void
	{
		$followers = $this->orm->tagFollowers->findByIds([[2, 2]]);

		Assert::same(1, $followers->count());

		/** @var TagFollower $follower */
		$follower = $followers->fetch();
		Assert::same(2, $follower->tag->id);
		Assert::same(2, $follower->author->id);

		$followers = $this->orm->tagFollowers->findByIds([[2, 2], [1, 3]])->orderBy('author');

		Assert::same(2, $followers->count());

		/** @var TagFollower $follower */
		$follower = $followers->fetch();
		Assert::same(3, $follower->tag->id);
		Assert::same(1, $follower->author->id);

		Assert::same(1, $this->orm->tagFollowers->findBy(['id!=' => [[2, 2], [1, 3]]])->count());
	}


	public function testPrimaryProxy(): void
	{
		/** @var Publisher $publisher */
		$publisher = $this->orm->publishers->getBy(['publisherId' => 1]);
		Assert::same('Nextras publisher A', $publisher->name);
		Assert::equal(1, $publisher->id);
	}


	public function testNonNullable(): void
	{
		Assert::throws(function (): void {
			$this->orm->books->findAll()->getByIdChecked(923);
		}, NoResultException::class);

		Assert::throws(function (): void {
			$this->orm->books->findAll()->getByChecked(['id' => 923]);
		}, NoResultException::class);

		Assert::type(Book::class, $this->orm->books->findAll()->getByIdChecked(1));
		Assert::type(Book::class, $this->orm->books->findAll()->getByChecked(['id' => 1]));
	}


	public function testMappingInCollection(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) Environment::skip('Test is only for Dbal mapper.');

		$tags = $this->orm->tags->findBy(['isGlobal' => true]);
		Assert::same(2, $tags->countStored());
		$fetched = $tags->fetch();
		Assert::notNull($fetched);
		Assert::same('Tag 1', $fetched->name);
	}


	public function testFindByNull(): void
	{
		$all = $this->orm->books->findBy(['printedAt' => null])->fetchAll();
		Assert::count(4, $all);
	}


	public function testDistinct(): void
	{
		$books = $this->orm->tagFollowers->findBy(['tag->books->id' => 1]);
		Assert::count(2, $books);
	}


	public function testToArrayCollection(): void
	{
		$c1 = $this->orm->authors->findAll();
		$c2 = $c1->toMemoryCollection();
		Assert::type(ArrayCollection::class, $c2);
		Assert::same($c2->count(), $c1->countStored());

		$author = $this->orm->authors->getByIdChecked(1);
		$c3 = $author->books->toCollection();
		$c4 = $c3->toMemoryCollection();
		if ($this->section === Helper::SECTION_ARRAY) {
			Assert::type(ArrayCollection::class, $c3);
		} else {
			Assert::type(DbalCollection::class, $c3);
		}
		Assert::type(ArrayCollection::class, $c4);
		Assert::same($c4->count(), $c3->countStored());

		$author->books->add(new Book());
		$c5 = $author->books->toCollection();
		$c6 = $c5->toMemoryCollection();
		Assert::type(HasManyCollection::class, $c5);
		Assert::type(ArrayCollection::class, $c6);
		Assert::same($c6->count(), $c5->countStored());

		$author = new Author();
		$c7 = $author->tagFollowers->toCollection();
		$c8 = $c7->toMemoryCollection();
		Assert::type(EmptyCollection::class, $c7);
		Assert::type(EmptyCollection::class, $c8);
		Assert::same($c8->count(), $c7->countStored());
	}
}


$test = new CollectionTest();
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionTest extends DataTestCase
{
	public function testCountOnOrdered()
	{
		$collection = $this->orm->books->findAll();
		$collection = $collection->orderBy('id');
		Assert::same(4, $collection->countStored());
	}


	public function testCountInCycle()
	{
		$ids = [];
		$books = $this->orm->authors->getById(1)->books;
		foreach ($books as $book) {
			$ids[] = $book->id;
			Assert::equal(2, $books->count());
		}
		sort($ids);
		Assert::equal([1, 2], $ids);
	}


	public function testCountOnLimited()
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


	public function testCountOnLimitedWithJoin()
	{
		$collection = $this->orm->books->findBy(['this->author->name' => 'Writer 1'])->orderBy('id')->limitBy(5);
		Assert::same(2, $collection->countStored());

		$collection = $this->orm->tagFollowers->findBy(['this->tag->name' => 'Tag 1'])->orderBy('tag')->limitBy(3);
		Assert::same(1, $collection->countStored());
	}


	public function testQueryByEntity()
	{
		$book = $this->orm->books->getById(1);
		$books = $this->orm->books->findBy(['id' => $book]);
		Assert::same(1, $books->countStored());
		Assert::same(1, $books->count());
	}


	public function testOrdering()
	{
		$books = $this->orm->books->findAll()
			->orderBy('this->author->id', ICollection::DESC)
			->orderBy('title', ICollection::ASC);

		$ids = [];
		foreach ($books as $book) {
			$ids[] = $book->id;
		}

		Assert::same([3, 4, 1, 2], $ids);


		$books = $this->orm->books->findAll()
			->orderBy('this->author->id', ICollection::DESC)
			->orderBy('title', ICollection::DESC);

		$ids = [];
		foreach ($books as $book) {
			$ids[] = $book->id;
		}
		Assert::same([4, 3, 2, 1], $ids);
	}


	public function testOrderingWithOptionalProperty()
	{
		$bookIds = $this->orm->books->findAll()
			->orderBy('this->translator->name')
			->orderBy('id', ICollection::DESC)
			->fetchPairs(null, 'id');

		if ($this->section === Helper::SECTION_PGSQL) {
			Assert::same([1, 4, 3, 2], $bookIds);
		} else {
			Assert::same([2, 1, 4, 3], $bookIds);
		}
	}


	public function testOrderingDateTimeImmutable()
	{
		$books = $this->orm->books->findAll()
			->orderBy('publishedAt', ICollection::DESC);

		$ids = [];
		foreach ($books as $book) {
			$ids[] = $book->id;
		}

		Assert::same([1, 3, 2, 4], $ids);
	}


	public function testEmptyArray()
	{
		$books = $this->orm->books->findBy(['id' => []]);
		Assert::same(0, $books->count());

		$books = $this->orm->books->findBy(['id!=' => []]);
		Assert::same(4, $books->count());
	}


	public function testConditionsInSameJoin()
	{
		$books = $this->orm->books->findBy([
			'this->author->name' => 'Writer 1',
			'this->author->web'  => 'http://example.com/1',
		]);

		Assert::same(2, $books->count());
	}


	public function testConditionsInDifferentJoinsAndSameTable()
	{
		$book = new Book();
		$this->orm->books->attach($book);

		$book->title = 'Books 5';
		$book->author = 1;
		$book->translator = 2;
		$book->publisher = 1;
		$this->orm->books->persistAndFlush($book);

		$books = $this->orm->books->findBy([
			'this->author->name' => 'Writer 1',
			'this->translator->web'  => 'http://example.com/2',
		]);

		Assert::same(1, $books->count());
	}


	public function testJoinDifferentPath()
	{
		$book3 = $this->orm->books->getById(3);

		$book3->ean = new Ean();
		$book3->ean->code = '123';
		$this->orm->persistAndFlush($book3);

		$book5 = new Book();
		$this->orm->books->attach($book5);

		$book5->title = 'Book 5';
		$book5->author = 1;
		$book5->publisher = 1;
		$book5->nextPart = 4;
		$book5->ean = new Ean();
		$book5->ean->code = '456';
		$this->orm->persistAndFlush($book5);

		$book4 = $this->orm->books->getById(4);

		$books = $this->orm->books->findBy([
			'this->nextPart->ean->code' => '123',
			'this->previousPart->ean->code' => '456',
		]);

		Assert::count(1, $books);

		Assert::same($book4, $books->fetch());
	}


	public function testCompositePK()
	{
		$followers = $this->orm->tagFollowers->findById([2, 2]);

		Assert::same(1, $followers->count());

		/** @var TagFollower $follower */
		$follower = $followers->fetch();
		Assert::same(2, $follower->tag->id);
		Assert::same(2, $follower->author->id);


		$followers = $this->orm->tagFollowers->findById([[2, 2], [3, 1]])->orderBy('author');

		Assert::same(2, $followers->count());

		/** @var TagFollower $follower */
		$follower = $followers->fetch();
		Assert::same(3, $follower->tag->id);
		Assert::same(1, $follower->author->id);


		Assert::same(1, $this->orm->tagFollowers->findBy(['id!=' => [[2, 2], [3, 1]]])->count());
	}


	public function testPrimaryProxy()
	{
		/** @var Publisher $publisher */
		$publisher = $this->orm->publishers->getBy(['publisherId' => 1]);
		Assert::same('Nextras publisher A', $publisher->name);
		Assert::equal(1, $publisher->id);
	}


	public function testMappingInCollection()
	{
		if ($this->section === 'array') Environment::skip('Test is only for Dbal mapper.');

		$tags = $this->orm->tags->findBy(['isGlobal' => true]);
		Assert::same(2, $tags->countStored());
		Assert::same('Tag 1', $tags->fetch()->name);
	}


	public function testFindByNull()
	{
		$all = $this->orm->books->findBy(['printedAt' => NULL])->fetchAll();
		Assert::count(4, $all);
	}


	public function testDistinct()
	{
		$books = $this->orm->tagFollowers->findBy(['this->tag->books->id' => 1]);
		Assert::count(2, $books);
	}
}


$test = new CollectionTest($dic);
$test->run();

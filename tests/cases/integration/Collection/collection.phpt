<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;

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
		$collection = $collection->limitBy(1, 1);
		Assert::same(1, $collection->count());

		$collection = $collection->limitBy(1, 10);
		Assert::same(0, $collection->count());


		$collection = $this->orm->books->findAll();
		$collection = $collection->limitBy(1, 1);
		Assert::same(1, $collection->countStored());

		$collection = $collection->limitBy(1, 10);
		Assert::same(0, $collection->countStored());
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
	}

}


$test = new CollectionTest($dic);
$test->run();

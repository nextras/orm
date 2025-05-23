<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\Aggregations\NoneAggregator;
use Nextras\Orm\Collection\Functions\AvgAggregateFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\Functions\MaxAggregateFunction;
use Nextras\Orm\Collection\Functions\MinAggregateFunction;
use Nextras\Orm\Collection\Functions\SumAggregateFunction;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAggregationTest extends DataTestCase
{
	public function testAvg(): void
	{
		$booksId = $this->orm->authors
			->findBy([
				CompareSmallerThanFunction::class,
				[AvgAggregateFunction::class, 'books->price->cents'],
				110,
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1], $booksId);

		$booksId = $this->orm->authors
			->findBy([
				CompareSmallerThanEqualsFunction::class,
				[AvgAggregateFunction::class, 'books->price->cents'],
				120,
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1, 2], $booksId);
	}


	public function testAvgOnEmptyCollection(): void
	{
		$author = new Author();
		$author->name = 'Test 3';

		$this->orm->persistAndFlush($author);

		$authorsId = $this->orm->authors
			->findAll()
			->orderBy([AvgAggregateFunction::class, 'books->price->cents'], ICollection::ASC_NULLS_FIRST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([3, 1, 2], $authorsId);
	}


	public function testCount(): void
	{
		$bookIds = $this->orm->books
			->findAll()
			->findBy([
				CompareGreaterThanEqualsFunction::class,
				[CountAggregateFunction::class, 'tags->id'],
				2,
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1, 2], $bookIds);

		$bookIds = $this->orm->books
			->findAll()
			->orderBy([CountAggregateFunction::class, 'tags->id'])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([4, 3, 1, 2], $bookIds);

		$bookIds = $this->orm->books
			->findAll()
			->orderBy([CountAggregateFunction::class, 'tags->id'], ICollection::DESC)
			->orderBy('id', ICollection::DESC)
			->fetchPairs(null, 'id');
		Assert::same([2, 1, 3, 4], $bookIds);
	}


	public function testMax(): void
	{
		$userIds = $this->orm->authors
			->findBy([
				CompareGreaterThanFunction::class,
				[MaxAggregateFunction::class, 'books->price->cents'],
				150,
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([2], $userIds);
	}


	public function testMaxWithEmptyCollection(): void
	{
		$author = new Author();
		$author->name = 'Test 3';

		$this->orm->persistAndFlush($author);

		$authorsId = $this->orm->authors
			->findAll()
			->orderBy([MaxAggregateFunction::class, 'books->price->cents'], ICollection::ASC_NULLS_FIRST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([3, 1, 2], $authorsId);
	}


	public function testMin(): void
	{
		$userIds = $this->orm->authors
			->findBy([
				CompareSmallerThanFunction::class,
				[MinAggregateFunction::class, 'books->price->cents'],
				50,
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([2], $userIds);
	}


	public function testMinWithEmptyCollection(): void
	{
		$author = new Author();
		$author->name = 'Test 3';

		$this->orm->persistAndFlush($author);

		$authorsId = $this->orm->authors
			->findAll()
			->orderBy([MinAggregateFunction::class, 'books->price->cents'], ICollection::ASC_NULLS_FIRST)
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([3, 2, 1], $authorsId);
	}


	public function testSum(): void
	{
		$userIds = $this->orm->authors
			->findBy([
				CompareSmallerThanEqualsFunction::class,
				[SumAggregateFunction::class, 'books->price->cents'],
				200,
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1], $userIds);
	}


	public function testAggregationWithNoAggregateCondition(): void
	{
		$users = $this->orm->authors->findBy([
			ICollection::OR,
			['books->title' => 'Book 1'],
			[
				CompareSmallerThanEqualsFunction::class,
				[CountAggregateFunction::class, 'tagFollowers->tag'],
				2,
			],
		]);
		Assert::same(2, $users->count());
		Assert::same(2, $users->countStored());
	}


	public function testMovingPrimaryTableConditionToWhenClause(): void
	{
		$books = $this->orm->books->findBy([
			ICollection::OR,
			['title' => 'Book 1'],
			[CompareGreaterThanFunction::class, [CountAggregateFunction::class, 'tags->id'], 0],
		]);
		Assert::same(3, $books->count()); // book #1, #2, #3

		$books = $this->orm->books->findBy([
			ICollection::AND,
			['title' => 'Book 1'],
			[CompareGreaterThanFunction::class, [CountAggregateFunction::class, 'tags->id'], 0],
		]);
		Assert::same(1, $books->count()); // book #1
	}


	public function testProperGroupByWhenLiftingNonAggregatedJoinCondition(): void
	{
		$books = $this->orm->books->findBy([
			ICollection::OR,
			['author->name' => 'Writer 1'],
			[CompareGreaterThanFunction::class, [CountAggregateFunction::class, 'tags->id'], 0],
		]);
		Assert::same(3, $books->count()); // book #1, #2, #3
	}


	public function testRowAggregatorImposingLifting(): void
	{
		$books = $this->orm->books->findBy([
			ICollection::OR,
			['title' => 'Book 1'], // book #1
			[ICollection::AND, new NoneAggregator(), 'tags->id' => 2], // book #3, #4
		]);
		Assert::same(3, $books->count());

		$books = $this->orm->books->findBy([
			ICollection::AND,
			['title' => 'Book 1'], // book #1
			[ICollection::AND, new NoneAggregator(), 'tags->id' => 2], // book #3, #4
		]);
		Assert::same(0, $books->count());

		$books = $this->orm->books->findBy([
			ICollection::AND,
			['title' => 'Book 1'], // book #1
			[ICollection::AND, new NoneAggregator(), 'tags->id' => 3], // book #1, #4
		]);
		Assert::same(1, $books->count());
	}
}


$test = new CollectionAggregationTest();
$test->run();

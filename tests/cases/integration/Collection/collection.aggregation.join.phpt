<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\Aggregations\AnyAggregator;
use Nextras\Orm\Collection\Aggregations\CountAggregator;
use Nextras\Orm\Collection\Aggregations\NoneAggregator;
use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAggregationJoinTest extends DataTestCase
{
	public function testAny(): void
	{
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			new AnyAggregator(),
			['books->title' => 'Book 1'],
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
		$author = $authors->fetch();
		Assert::notNull($author);
		Assert::same(1, $author->id);

		// implicit any
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			['books->title' => 'Book 1'],
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
		$author = $authors->fetch();
		Assert::notNull($author);
		Assert::same(1, $author->id);
	}


	public function testIndependentAnyOverManyHasMany(): void
	{
		$books = $this->orm->books->findBy([
			ICollection::AND,
			[ICollection::AND, new AnyAggregator('1'), 'tags->id' => 1],
			[ICollection::AND, new AnyAggregator('2'), 'tags->id' => 2],
		]);
		Assert::same(1, $books->count());

		$books = $this->orm->books->findBy([
			ICollection::AND,
			[ICollection::AND, new AnyAggregator('3'), 'tags->id' => 3],
			[CompareEqualsFunction::class, [CountAggregateFunction::class, 'tags->id'], 1],
		]);
		Assert::same(1, $books->count());
	}


	public function testIndependentAnyWithGroupingOverPk(): void
	{
		// Select books that:
		// - have tags 2 OR 3
		// - AND have more than 1 tags
		// Matches books #1, #2
		$books = $this->orm->books->findBy([
			ICollection::AND,
			['tags->id' => [2, 3]],
			[CompareGreaterThanFunction::class, [CountAggregateFunction::class, 'tags->id'], 1],
		]);
		Assert::same(2, $books->count());

		// Select books that:
		// - have tags 2 OR 3
		// - OR have more than 1 tags
		// Matches books #1, #2, #3
		$books = $this->orm->books->findBy([
			ICollection::OR,
			['tags->id' => [2, 3]],
			[CompareGreaterThanFunction::class, [CountAggregateFunction::class, 'tags->id'], 1],
		]);
		Assert::same(3, $books->count());
	}


	public function testAnyDependent(): void
	{
		/*
		 * Select author that has a book that:
		 * - has title Book 1
		 * - and is not translated.
		 */
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			'books->title' => 'Book 1',
			'books->translator->id' => null,
		]);
		Assert::same(0, $authors->count());
		Assert::same(0, $authors->countStored());

		/*
		 * Select author that has exactly 1 book that:
		 * - has been translated
		 * - or has a price lower than 100.
		 *
		 * This test covers dependent comparison in OR operator function.
		 */
		$authors = $this->orm->authors->findBy([
			ICollection::OR,
			new CountAggregator(atLeast: 1, atMost: 1),
			'books->translator->id!=' => null,
			'books->price->cents<' => 100,
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
	}


	public function testCountAggregator(): void
	{
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			new CountAggregator(atLeast: 2, atMost: null),
			'books->price->cents>=' => 50,
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());

		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			new CountAggregator(atLeast: null, atMost: 1),
			'books->price->cents>=' => 51,
		]);
		Assert::same(2, $authors->count());
		Assert::same(2, $authors->countStored());
	}


	public function testHasValueOrEmptyWithFunctions(): void
	{
		/*
		 * Selects books where book:
		 * - has a tag with id 1
		 * - or has no tags
		 */
		$books = $this->orm->books->findBy([
			ICollection::OR,
			['tags->id' => [1]],
			[CompareEqualsFunction::class, [CountAggregateFunction::class, 'tags->id'], 0],
		]);

		Assert::same(2, $books->count());
		Assert::same(2, $books->countStored());
	}


	public function testNone(): void
	{
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			new NoneAggregator(),
			['books->title' => 'Book 1'],
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
		$author = $authors->fetch();
		Assert::notNull($author);
		Assert::same(2, $author->id);
	}


	public function testIndependentSelects(): void
	{
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			[
				ICollection::AND,
				new AnyAggregator('any1'),
				'books->title' => 'Book 1',
				'books->price->cents' => 50,
			],
			[
				ICollection::AND,
				new AnyAggregator('any2'),
				'books->title' => 'Book 2',
				'books->price->cents' => 150,
			],
		]);

		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
	}
}


(new CollectionAggregationJoinTest())->run();

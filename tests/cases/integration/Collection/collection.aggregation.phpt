<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Nextras\Orm\Collection\Functions\AvgAggregateFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\Functions\MaxAggregateFunction;
use Nextras\Orm\Collection\Functions\MinAggregateFunction;
use Nextras\Orm\Collection\Functions\SumAggregateFunction;
use Nextras\Orm\Collection\Functions\ValueOperatorFunction;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAggregationTest extends DataTestCase
{
	public function testAvg()
	{
		$booksId = $this->orm->authors
			->findBy([
				ValueOperatorFunction::class,
				'<',
				[AvgAggregateFunction::class, 'books->price->cents'],
				110
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1], $booksId);

		$booksId = $this->orm->authors
			->findBy([
				ValueOperatorFunction::class,
				'<=',
				[AvgAggregateFunction::class, 'books->price->cents'],
				120
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1, 2], $booksId);
	}

	public function testCount()
	{
		$bookIds = $this->orm->books
			->findAll()
			->findBy([
				ValueOperatorFunction::class,
				'>=',
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


	public function testMax()
	{
		$userIds = $this->orm->authors
			->findBy([
				ValueOperatorFunction::class,
				'>',
				[MaxAggregateFunction::class, 'books->price->cents'],
				150
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([2], $userIds);
	}


	public function testMin()
	{
		$userIds = $this->orm->authors
			->findBy([
				ValueOperatorFunction::class,
				'<',
				[MinAggregateFunction::class, 'books->price->cents'],
				50
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([2], $userIds);
	}


	public function testSum()
	{
		$userIds = $this->orm->authors
			->findBy([
				ValueOperatorFunction::class,
				'<=',
				[SumAggregateFunction::class, 'books->price->cents'],
				200
			])
			->orderBy('id')
			->fetchPairs(null, 'id');
		Assert::same([1], $userIds);
	}
}


$test = new CollectionAggregationTest($dic);
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;


use ArrayIterator;
use Mockery;
use Nextras\Orm\Collection\DbalCollection;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


class DbalCollectionTest extends TestCase
{
	public function testFetch(): void
	{
		$collection = Mockery::mock(DbalCollection::class)->makePartial();
		$collection->shouldReceive('getIterator')->andReturn(new ArrayIterator([
			$e1 = $this->e(Book::class),
			$e2 = $this->e(Book::class),
			$e3 = $this->e(Book::class),
		]));

		Assert::same($e1, $collection->fetch());
		Assert::same($e2, $collection->fetch());
		Assert::same($e3, $collection->fetch());
		Assert::null($collection->fetch());
	}


	public function testFetchAllAndCount(): void
	{
		$collection = Mockery::mock(DbalCollection::class)->makePartial();
		$collection->shouldReceive('getIterator')->andReturn(new ArrayIterator([
			$e1 = $this->e(Book::class),
			$e2 = $this->e(Book::class),
			$e3 = $this->e(Book::class),
		]));

		Assert::same([$e1, $e2, $e3], $collection->fetchAll());
		Assert::same(3, count($collection));
	}
}


$test = new DbalCollectionTest();
$test->run();

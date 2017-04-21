<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use ArrayIterator;
use Mockery;
use Nextras\Orm\Mapper\Dbal\DbalCollection;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class DbalCollectionTest extends TestCase
{

	public function testFetch()
	{
		$collection = Mockery::mock(DbalCollection::class)->makePartial();
		$collection->shouldReceive('getIterator')->andReturn(new ArrayIterator([2, 3, 4]));

		Assert::same(2, $collection->fetch());
		Assert::same(3, $collection->fetch());
		Assert::same(4, $collection->fetch());
		Assert::null($collection->fetch());
	}


	public function testFetchAllAndCount()
	{
		$collection = Mockery::mock(DbalCollection::class)->makePartial();
		$collection->shouldReceive('getIterator')->andReturn(new ArrayIterator([2, 3, 4]));

		Assert::same([2, 3, 4], $collection->fetchAll());
		Assert::same(3, count($collection));
	}

}


$test = new DbalCollectionTest($dic);
$test->run();

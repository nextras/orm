<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Collection;

use ArrayIterator;
use Mockery;
use Nextras\Orm\Collection\Collection;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class CollectionTest extends TestCase
{

	public function testFetch()
	{
		$collectionMapper = Mockery::mock('Nextras\Orm\Mapper\ICollectionMapper');
		$collectionMapper->shouldReceive('getIterator')->andReturn(new ArrayIterator([2, 3, 4]));

		$collection = new Collection($collectionMapper);

		Assert::same(2, $collection->fetch());
		Assert::same(3, $collection->fetch());
		Assert::same(4, $collection->fetch());
		Assert::null($collection->fetch());
	}


	public function testFetchAllAndCount()
	{
		$collectionMapper = Mockery::mock('Nextras\Orm\Mapper\ICollectionMapper');
		$collectionMapper->shouldReceive('getIterator')->andReturn(new ArrayIterator([2, 3, 4]));
		$collectionMapper->shouldReceive('getIteratorCount')->andReturn(3);

		$collection = new Collection($collectionMapper);
		Assert::same([2, 3, 4], $collection->fetchAll());
		Assert::same(3, count($collection));
	}

}


$test = new CollectionTest($dic);
$test->run();

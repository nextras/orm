<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

use Mockery;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MultiEntityIteratorTest extends TestCase
{
	public function testSubarrayIterator()
	{
		$data = [
			10 => [Mockery::mock(IEntityHasPreloadContainer::class), Mockery::mock(IEntityHasPreloadContainer::class)],
			12 => [Mockery::mock(IEntityHasPreloadContainer::class), Mockery::mock(IEntityHasPreloadContainer::class)],
		];
		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('hasProperty')->once()->andReturn(true);
		$metadata->shouldReceive('hasProperty')->once()->andReturn(false);
		$metadata->shouldReceive('hasProperty')->twice()->andReturn(true);
		$data[10][0]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[10][0]->shouldReceive('getRawValue')->once()->with('id')->andReturn(123);
		$data[10][1]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[12][0]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[12][0]->shouldReceive('getRawValue')->once()->with('id')->andReturn(321);
		$data[12][1]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[12][1]->shouldReceive('getRawValue')->once()->with('id')->andReturn(456);

		$iterator = new MultiEntityIterator($data);
		$iterator->setDataIndex(12);

		Assert::same(2, count($iterator));

		$data[12][0]->shouldReceive('setPreloadContainer')->once()->with($iterator);
		$data[12][1]->shouldReceive('setPreloadContainer')->once()->with($iterator);

		Assert::same($data[12], iterator_to_array($iterator));
		Assert::same([123, 321, 456], $iterator->getPreloadValues('id'));

		$iterator->setDataIndex(13);
		Assert::same(0, count($iterator));
		Assert::same([], iterator_to_array($iterator));
	}
}


$test = new MultiEntityIteratorTest($dic);
$test->run();

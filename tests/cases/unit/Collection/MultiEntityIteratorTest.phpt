<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use Mockery;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class MultiEntityIteratorTest extends TestCase
{
	public function testSubarrayIterator(): void
	{
		$data = [
			10 => [Mockery::mock(Entity::class), Mockery::mock(Entity::class)],
			12 => [Mockery::mock(Entity::class), Mockery::mock(Entity::class)],
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


	public function testClonesSharePreloadCache(): void
	{
		$data = [
			10 => [Mockery::mock(Entity::class)],
			12 => [Mockery::mock(Entity::class)],
		];
		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('hasProperty')->twice()->andReturn(true);
		$data[10][0]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[10][0]->shouldReceive('getRawValue')->once()->with('id')->andReturn(123);
		$data[12][0]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[12][0]->shouldReceive('getRawValue')->once()->with('id')->andReturn(321);

		$iterator = new MultiEntityIterator($data);

		$first = clone $iterator;
		$first->setDataIndex(10);
		$second = clone $iterator;
		$second->setDataIndex(13);

		Assert::same([123, 321], $first->getPreloadValues('id'));
		Assert::same([123, 321], $second->getPreloadValues('id'));
		Assert::same([123, 321], $iterator->getPreloadValues('id'));
		Assert::same(1, count($first));
		Assert::same(0, count($second));
	}
}


$test = new MultiEntityIteratorTest();
$test->run();

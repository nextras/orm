<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use Mockery;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;
use function count;
use function iterator_to_array;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MultiEntityIteratorTest extends TestCase
{
	public function testSubarrayIterator(): void
	{
		$data = [
			10 => [Mockery::mock(Entity::class), Mockery::mock(Entity::class)],
			12 => [Mockery::mock(Entity::class), Mockery::mock(Entity::class)],
		];
		$data[10][0]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(123);
		$data[10][1]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(null);
		$data[12][0]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(321);
		$data[12][1]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(456);

		$iterator = new MultiEntityIterator($data);
		$iterator->setDataIndex(12);

		Assert::same(2, count($iterator));

		$data[12][0]->shouldReceive('setPreloadContainer')->once()->with($iterator);
		$data[12][1]->shouldReceive('setPreloadContainer')->once()->with($iterator);

		$idPropertyMetadata = new PropertyMetadata();
		$idPropertyMetadata->name = 'id';

		Assert::same($data[12], iterator_to_array($iterator));
		Assert::same([123, 321, 456], $iterator->getPreloadValues($idPropertyMetadata));

		$iterator->setDataIndex(13);
		Assert::same(0, count($iterator));
		Assert::same([], iterator_to_array($iterator));
	}
}


$test = new MultiEntityIteratorTest($dic);
$test->run();

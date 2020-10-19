<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use Mockery;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;
use function count;
use function iterator_to_array;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityIteratorTest extends TestCase
{
	public function testSimpleArray(): void
	{
		$data = [
			Mockery::mock(Entity::class),
			Mockery::mock(Entity::class),
			Mockery::mock(Entity::class),
			Mockery::mock(Entity::class),
		];
		$data[0]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(123);
		$data[1]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(321);
		$data[2]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(null);
		$data[3]->shouldReceive('getRawValue')->once()->with('id', false)->andReturn(789);

		$iterator = new EntityIterator($data);
		Assert::same(4, count($iterator));

		$data[0]->shouldReceive('setPreloadContainer')->twice()->with($iterator);
		$data[1]->shouldReceive('setPreloadContainer')->twice()->with($iterator);
		$data[2]->shouldReceive('setPreloadContainer')->twice()->with($iterator);
		$data[3]->shouldReceive('setPreloadContainer')->twice()->with($iterator);

		Assert::same($data, iterator_to_array($iterator));
		Assert::same($data, iterator_to_array($iterator)); // check iterator rewind

		$idPropertyMetadata = new PropertyMetadata();
		$idPropertyMetadata->name = 'id';

		Assert::same([123, 321, 789], $iterator->getPreloadValues($idPropertyMetadata));
	}


	public function testIteratorOverflow(): void
	{
		$data = [Mockery::mock(Entity::class)];

		$iterator = new EntityIterator($data);
		$data[0]->shouldReceive('setPreloadContainer')->once()->with($iterator);
		Assert::same($data, iterator_to_array($iterator));

		$iterator->next();
		Assert::false($iterator->valid());
	}
}


$test = new EntityIteratorTest($dic);
$test->run();

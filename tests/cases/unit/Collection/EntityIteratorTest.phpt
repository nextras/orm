<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

use Mockery;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityIteratorTest extends TestCase
{
	public function testSimpleArray()
	{
		$data = [
			Mockery::mock(IEntityHasPreloadContainer::class),
			Mockery::mock(IEntityHasPreloadContainer::class),
			Mockery::mock(IEntityHasPreloadContainer::class),
			Mockery::mock(IEntityHasPreloadContainer::class),
		];
		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('hasProperty')->twice()->andReturn(true);
		$metadata->shouldReceive('hasProperty')->once()->andReturn(false);
		$metadata->shouldReceive('hasProperty')->once()->andReturn(true);
		$data[0]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[0]->shouldReceive('getRawValue')->once()->with('id')->andReturn(123);
		$data[1]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[1]->shouldReceive('getRawValue')->once()->with('id')->andReturn(321);
		$data[2]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[3]->shouldReceive('getMetadata')->once()->andReturn($metadata);
		$data[3]->shouldReceive('getRawValue')->once()->with('id')->andReturn(789);

		$iterator = new EntityIterator($data);
		Assert::same(4, count($iterator));

		$data[0]->shouldReceive('setPreloadContainer')->twice()->with($iterator);
		$data[1]->shouldReceive('setPreloadContainer')->twice()->with($iterator);
		$data[2]->shouldReceive('setPreloadContainer')->twice()->with($iterator);
		$data[3]->shouldReceive('setPreloadContainer')->twice()->with($iterator);

		Assert::same($data, iterator_to_array($iterator));
		Assert::same($data, iterator_to_array($iterator)); // check iterator rewind
		Assert::same([123, 321, 789], $iterator->getPreloadValues('id'));
	}


	public function testIteratorOverflow()
	{
		$data = [Mockery::mock(IEntityHasPreloadContainer::class)];
		$data[0]->id = 123;

		$iterator = new EntityIterator($data);
		$data[0]->shouldReceive('setPreloadContainer')->once()->with($iterator);
		Assert::same($data, iterator_to_array($iterator));

		$iterator->next();
		Assert::null($iterator->current());
	}
}


$test = new EntityIteratorTest($dic);
$test->run();

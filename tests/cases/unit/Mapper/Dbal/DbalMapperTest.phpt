<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use ArrayIterator;
use Mockery;
use Nextras\Orm\Collection\ArrayCollection;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class DbalMapperTest extends TestCase
{

	public function testToCollectionArray()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		$mapper = Mockery::mock('Nextras\Orm\Mapper\Dbal\DbalMapper')->makePartial();
		$mapper->shouldReceive('getRepository')->once()->andReturn($repository);

		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 1])->andReturn((object) ['id' => 1]);
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 2])->andReturn((object) ['id' => 2]);
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 3])->andReturn((object) ['id' => 3]);

		/** @var ArrayCollection $collection */
		$collection = $mapper->toCollection([
			['id' => 1],
			['id' => 2],
			['id' => 3],
		]);

		Assert::type('Nextras\Orm\Collection\ArrayCollection', $collection);

		$reflection = new \ReflectionProperty('Nextras\Orm\Collection\ArrayCollection', 'data');
		$reflection->setAccessible(TRUE);
		$data = $reflection->getValue($collection);

		Assert::same(3, count($data));
		Assert::equal((object) ['id' => 1], $data[0]);
		Assert::equal((object) ['id' => 2], $data[1]);
		Assert::equal((object) ['id' => 3], $data[2]);
	}


	public function testToCollectionResult()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		$mapper = Mockery::mock('Nextras\Orm\Mapper\Dbal\DbalMapper')->makePartial();
		$mapper->shouldReceive('getRepository')->once()->andReturn($repository);

		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 1])->andReturn((object) ['id' => 1]);
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 2])->andReturn((object) ['id' => 2]);
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 3])->andReturn((object) ['id' => 3]);

		$row = Mockery::mock('Nextras\Dbal\Result\Row');
		$row->shouldReceive('toArray')->once()->andReturn(['id' => 1]);
		$row->shouldReceive('toArray')->once()->andReturn(['id' => 2]);
		$row->shouldReceive('toArray')->once()->andReturn(['id' => 3]);

		$result = Mockery::mock('Nextras\Dbal\Result\Result');
		$result->shouldReceive('rewind')->once();
		$result->shouldReceive('valid')->times(3)->andReturn(TRUE);
		$result->shouldReceive('current')->times(3)->andReturn($row);
		$result->shouldReceive('next')->times(3);
		$result->shouldReceive('valid')->once()->andReturn(FALSE);

		/** @var ArrayCollection $collection */
		$collection = $mapper->toCollection($result);

		Assert::type('Nextras\Orm\Collection\ArrayCollection', $collection);

		$reflection = new \ReflectionProperty('Nextras\Orm\Collection\ArrayCollection', 'data');
		$reflection->setAccessible(TRUE);
		$data = $reflection->getValue($collection);

		Assert::same(3, count($data));
		Assert::equal((object) ['id' => 1], $data[0]);
		Assert::equal((object) ['id' => 2], $data[1]);
		Assert::equal((object) ['id' => 3], $data[2]);


		Assert::throws(function() use ($mapper) {
			$mapper->toCollection(new ArrayCollection([]));
		}, 'Nextras\Orm\InvalidArgumentException');
	}

}


$test = new DbalMapperTest($dic);
$test->run();

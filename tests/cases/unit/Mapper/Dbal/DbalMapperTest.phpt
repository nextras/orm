<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\StorageReflection\StorageReflection;
use Nextras\Orm\Repository\IRepository;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class DbalMapperTest extends TestCase
{

	public function testToCollectionArray()
	{
		$repository = Mockery::mock(IRepository::class);

		$mapper = Mockery::mock(DbalMapper::class)->makePartial();
		$mapper->shouldReceive('getRepository')->twice()->andReturn($repository);
		$storageReflection = Mockery::mock(StorageReflection::class);
		$storageReflection->shouldReceive('convertStorageToEntity')->andReturnUsing(function ($value) {
			return $value;
		});

		$mapper->shouldReceive('getStorageReflection')->andReturn($storageReflection);

		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 1])->andReturn($a = new Author());
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 2])->andReturn($b = new Author());
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 3])->andReturn($c = new Author());

		/** @var ArrayCollection $collection */
		$collection = $mapper->toCollection([
			['id' => 1],
			['id' => 2],
			['id' => 3],
		]);

		Assert::type(ArrayCollection::class, $collection);

		$reflection = new \ReflectionProperty(ArrayCollection::class, 'data');
		$reflection->setAccessible(true);
		$data = $reflection->getValue($collection);

		Assert::same(3, count($data));
		Assert::equal($a, $data[0]);
		Assert::equal($b, $data[1]);
		Assert::equal($c, $data[2]);
	}


	public function testToCollectionResult()
	{
		$repository = Mockery::mock(IRepository::class);

		$mapper = Mockery::mock(DbalMapper::class)->makePartial();
		$mapper->shouldReceive('getRepository')->twice()->andReturn($repository);
		$storageReflection = Mockery::mock(StorageReflection::class);
		$storageReflection->shouldReceive('convertStorageToEntity')->andReturnUsing(function ($value) {
			return $value;
		});

		$mapper->shouldReceive('getStorageReflection')->andReturn($storageReflection);

		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 1])->andReturn($a = new Author());
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 2])->andReturn($b = new Author());
		$repository->shouldReceive('hydrateEntity')->once()->with(['id' => 3])->andReturn($c = new Author());

		$row = Mockery::mock(Row::class);
		$row->shouldReceive('toArray')->once()->andReturn(['id' => 1]);
		$row->shouldReceive('toArray')->once()->andReturn(['id' => 2]);
		$row->shouldReceive('toArray')->once()->andReturn(['id' => 3]);

		$result = Mockery::mock(Result::class);
		$result->shouldReceive('rewind')->once();
		$result->shouldReceive('valid')->times(3)->andReturn(true);
		$result->shouldReceive('current')->times(3)->andReturn($row);
		$result->shouldReceive('next')->times(3);
		$result->shouldReceive('valid')->once()->andReturn(false);

		/** @var ArrayCollection $collection */
		$collection = $mapper->toCollection($result);

		Assert::type(ArrayCollection::class, $collection);

		$reflection = new \ReflectionProperty(ArrayCollection::class, 'data');
		$reflection->setAccessible(true);
		$data = $reflection->getValue($collection);

		Assert::same(3, count($data));
		Assert::equal($a, $data[0]);
		Assert::equal($b, $data[1]);
		Assert::equal($c, $data[2]);


		Assert::throws(function () use ($mapper) {
			$mapper->toCollection(new ArrayCollection([], $this->orm->authors));
		}, InvalidArgumentException::class);
	}

}


$test = new DbalMapperTest($dic);
$test->run();

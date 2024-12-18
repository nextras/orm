<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;


use Mockery;
use Nextras\Orm\Entity\AbstractEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Repository\IRepository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @property int    $id {primary}
 * @property string $name
 * @property int    $age
 */
class DataEntityFragmentIsModifiedTest extends AbstractEntity
{
	public function __construct(EntityMetadata $metadata)
	{
		$this->metadata = $metadata;
		parent::__construct();
	}


	protected function createMetadata(): EntityMetadata
	{
		return $this->metadata;
	}
}


class AbstractEntityIsModifiedTest extends TestCase
{

	public function testNewEntity(): void
	{
		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('getProperty')->with('property');

		$entity = new DataEntityFragmentIsModifiedTest($metadata);
		Assert::true($entity->isModified());
		Assert::true($entity->isModified('property'));
	}


	public function testLoadedEntity(): void
	{
		$repository = Mockery::mock(IRepository::class);

		$idPropertyMetadata = Mockery::mock(PropertyMetadata::class);
		$idPropertyMetadata->wrapper = null;
		$idPropertyMetadata->shouldReceive('isValid')->with(1)->andReturn(true);

		$agePropertyMetadata = Mockery::mock(PropertyMetadata::class);
		$agePropertyMetadata->isReadonly = false;
		$agePropertyMetadata->shouldReceive('isValid')->with(34)->andReturn(true);
		$agePropertyMetadata->shouldReceive('isValid')->with(20)->andReturn(true);

		$namePropertyMetadata = Mockery::mock(PropertyMetadata::class);

		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('getProperties')->once()->andReturn([
			'id' => $idPropertyMetadata,
			'name' => $agePropertyMetadata,
			'age' => $namePropertyMetadata,
		]);
		$metadata->shouldReceive('getProperty')->with('age')->times(4)->andReturn($agePropertyMetadata);
		$metadata->shouldReceive('getProperty')->with('name')->times(2);

		/** @var DataEntityFragmentIsModifiedTest&Mockery\MockInterface $entity */
		$entity = Mockery::mock(DataEntityFragmentIsModifiedTest::class)->makePartial();
		$entity->shouldReceive('getValue')->with('id')->andReturn([1]);
		$entity->onAttach($repository, $metadata);
		$entity->onLoad(
			[
				'id' => 1,
				'name' => 'Jon Snow',
				'age' => 34,
			]
		);

		Assert::false($entity->isModified());
		Assert::false($entity->isModified('age'));

		$entity->setValue('age', 20); // @phpstan-ignore nextrasOrm.propertyNotFound

		Assert::true($entity->isModified());
		Assert::true($entity->isModified('age'));
		Assert::false($entity->isModified('name'));

		$idPropertyMetadata = Mockery::mock(PropertyMetadata::class);
		$idPropertyMetadata->isReadonly = false;
		$idPropertyMetadata->shouldReceive('isValid')->with('1')->andReturn(true);
		$metadata->shouldReceive('getProperty')->with('id')->once()->andReturn($idPropertyMetadata);
		$entity->onPersist(1);

		Assert::false($entity->isModified());
		Assert::false($entity->isModified('age'));
		Assert::false($entity->isModified('name'));
	}

}


$test = new AbstractEntityIsModifiedTest();
$test->run();

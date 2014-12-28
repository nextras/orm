<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\Fragments\DataEntityFragment;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


abstract class DataEntityFragmentIsModifiedTest extends DataEntityFragment
{
	public function __construct(EntityMetadata $metadata)
	{
		parent::__construct();
		$this->metadata = $metadata;
	}
	protected function createMetadata() {}
}


class DataEntityFragmentIsModifiedTestCase extends TestCase
{

	public function testNewEntity()
	{
		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');
		$metadata->shouldReceive('getProperty')->with('property');

		/** @var IEntity $entity */
		$entity = Mockery::mock('NextrasTests\Orm\Entity\Fragments\DataEntityFragmentIsModifiedTest')->makePartial();
		$entity->__construct($metadata);

		Assert::true($entity->isModified());
		Assert::true($entity->isModified('property'));
	}


	public function testLoadedEntity()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		$idPropertyMetadata  = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$idPropertyMetadata->container = NULL;
		$idPropertyMetadata->shouldReceive('isValid')->with(1)->andReturn(TRUE);

		$agePropertyMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$agePropertyMetadata->isReadonly = FALSE;
		$agePropertyMetadata->shouldReceive('isValid')->with(34)->andReturn(TRUE);
		$agePropertyMetadata->shouldReceive('isValid')->with(20)->andReturn(TRUE);

		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');
		$metadata->shouldReceive('getPrimaryKey')->twice()->andReturn(['id']);
		$metadata->shouldReceive('getStorageProperties')->once()->andReturn(['id', 'name', 'age']);
		$metadata->shouldReceive('getProperty')->with('id')->twice()->andReturn($idPropertyMetadata);
		$metadata->shouldReceive('getProperty')->with('age')->times(4)->andReturn($agePropertyMetadata);
		$metadata->shouldReceive('getProperty')->with('name')->twice();

		/** @var IEntity $entity */
		$entity = Mockery::mock('NextrasTests\Orm\Entity\Fragments\DataEntityFragmentIsModifiedTest')->makePartial();
		$entity->shouldReceive('getValue')->with('id')->andReturn([1]);
		$entity->fireEvent('onLoad', [
			$repository,
			$metadata,
			[
				'id' => 1,
				'name' => 'Jon Snow',
				'age' => 34,
			],
		]);

		Assert::false($entity->isModified());
		Assert::false($entity->isModified('age'));

		$entity->setValue('age', 20);

		Assert::true($entity->isModified());
		Assert::true($entity->isModified('age'));
		Assert::false($entity->isModified('name'));


		$propertyIdMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyIdMetadata->isReadonly = FALSE;
		$propertyIdMetadata->shouldReceive('isValid')->with('1')->andReturn(TRUE);

		$metadata->shouldReceive('getPrimaryKey')->once()->andReturn(['id']);
		$metadata->shouldReceive('getProperty')->with('id')->once()->andReturn($propertyIdMetadata);
		$entity->fireEvent('onPersist', [1]);

		Assert::false($entity->isModified());
		Assert::false($entity->isModified('age'));
		Assert::false($entity->isModified('name'));
	}

}


$test = new DataEntityFragmentIsModifiedTestCase($dic);
$test->run();

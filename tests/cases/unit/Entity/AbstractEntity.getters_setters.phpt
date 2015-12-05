<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\AbstractEntity;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


abstract class GetterSetterTestEntity extends AbstractEntity
{
	public function setMetadata(EntityMetadata $metadata)
	{
		$this->metadata = $metadata;
	}
	protected function createMetadata() {}
	protected function setterIsMain($val)
	{
		return $val === 'Yes';
	}
	protected function getterIsMain($val)
	{
		return $val ? 'Yes' : NULL;
	}
}


class AbstractEntityGettersSettersTest extends TestCase
{

	public function testBasics()
	{
		$propertyMetadata = Mockery::mock(PropertyMetadata::class);
		$propertyMetadata->name = 'isMain';
		$propertyMetadata->hasSetter = 'setterIsMain';
		$propertyMetadata->hasGetter = 'getterIsMain';
		$propertyMetadata->isNullable = TRUE;
		$propertyMetadata->shouldReceive('isValid')->with(FALSE)->twice()->andReturn(TRUE);
		$propertyMetadata->shouldReceive('isValid')->with(TRUE)->once()->andReturn(TRUE);

		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('getProperty')->with('isMain')->andReturn($propertyMetadata);

		/** @var IEntity $entity */
		$entity = Mockery::mock(GetterSetterTestEntity::class)->makePartial();
		$entity->setMetadata($metadata);

		$entity->setValue('isMain', 'yes');
		Assert::null($entity->getValue('isMain'));

		$entity->setValue('isMain', NULL);
		Assert::null($entity->getValue('isMain'));

		$entity->setValue('isMain', 'Yes');
		Assert::same('Yes', $entity->getValue('isMain'));

		$propertyReflection = new \ReflectionProperty(AbstractEntity::class, 'data');
		$propertyReflection->setAccessible(TRUE);

		Assert::same([
			'isMain' => TRUE,
		], $propertyReflection->getValue($entity));
	}

}


$test = new AbstractEntityGettersSettersTest($dic);
$test->run();

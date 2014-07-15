<?php

namespace Nextras\Orm\Tests\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\Fragments\DataEntityFragment;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


abstract class GetterSetterTestEntity extends DataEntityFragment
{
	public function setMetadata(EntityMetadata $metadata)
	{
		$this->metadata = $metadata;
	}
	protected function createMetadata() {}
	public function setIsMain($val)
	{
		$this->setValue('isMain', $val === 'Yes');
	}
	public function getIsMain()
	{
		return $this->getValue('isMain') ? 'Yes' : NULL;
	}
}


/**
 * @testCase
 */
class DataEntityFragmentGettersSettersTestCase extends TestCase
{

	public function testBasics()
	{
		$propertyMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata->hasSetter = TRUE;
		$propertyMetadata->hasGetter = TRUE;
		$propertyMetadata->shouldReceive('isValid')->with(FALSE)->twice()->andReturn(TRUE);
		$propertyMetadata->shouldReceive('isValid')->with(TRUE)->once()->andReturn(TRUE);

		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');
		$metadata->shouldReceive('getProperty')->with('isMain')->andReturn($propertyMetadata);

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\GetterSetterTestEntity')->makePartial();
		$entity->setMetadata($metadata);

		$entity->setValue('isMain', 'yes');
		Assert::null($entity->getValue('isMain'));

		$entity->setValue('isMain', NULL);
		Assert::null($entity->getValue('isMain'));

		$entity->setValue('isMain', 'Yes');
		Assert::same('Yes', $entity->getValue('isMain'));
	}

}


$test = new DataEntityFragmentGettersSettersTestCase($dic);
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;


use Mockery;
use Nextras\Orm\Entity\AbstractEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @property string|null $isMain
 */
class GetterSetterTestEntity extends AbstractEntity
{
	public function setMetadata(EntityMetadata $metadata): void
	{
		$this->metadata = $metadata;
	}


	protected function createMetadata(): EntityMetadata
	{
		$propertyMetadata = Mockery::mock(PropertyMetadata::class);
		$propertyMetadata->name = 'isMain';
		$propertyMetadata->hasSetter = 'setterIsMain';
		$propertyMetadata->hasGetter = 'getterIsMain';
		$propertyMetadata->isNullable = true;
		$propertyMetadata->shouldReceive('isValid')->with(false)->twice()->andReturn(true);
		$propertyMetadata->shouldReceive('isValid')->with(true)->once()->andReturn(true);

		$metadata = Mockery::mock(EntityMetadata::class);
		$metadata->shouldReceive('getProperty')->with('isMain')->andReturn($propertyMetadata);
		$metadata->shouldReceive('getProperties')->andReturn(['isMain' => $propertyMetadata]);
		return $metadata;
	}


	protected function setterIsMain(?string $val): bool
	{
		return $val === 'Yes';
	}


	protected function getterIsMain(bool $val): ?string
	{
		return $val ? 'Yes' : null;
	}
}


class AbstractEntityGettersSettersTest extends TestCase
{
	public function testBasics(): void
	{
		$entity = new GetterSetterTestEntity();

		$entity->setValue('isMain', 'yes');
		Assert::null($entity->getValue('isMain'));

		$entity->setValue('isMain', null);
		Assert::null($entity->getValue('isMain'));

		$entity->setValue('isMain', 'Yes');
		Assert::same('Yes', $entity->getValue('isMain'));

		Assert::same([
			'isMain' => true,
		], $entity->getRawValues());
	}
}


$test = new AbstractEntityGettersSettersTest($dic);
$test->run();

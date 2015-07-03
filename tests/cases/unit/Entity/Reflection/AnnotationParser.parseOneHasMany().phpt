<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Repository\Repository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property mixed $test1 {1:m BarRepository}
 * @property mixed $test2 {1:n BarRepository $property}
 *
 * @property mixed $test3 {1:m BarRepository order:this->entity->id}
 * @property mixed $test4 {1:n BarRepository $property order:id,DESC}
 *
 * @property mixed $test13 {1:m Bar order:this->entity->id}
 * @property mixed $test14 {1:n Bar::$property order:id,DESC}
 */
class OneHasManyTestEntity
{}


class BarRepository extends Repository
{
	public static function getEntityClassNames()
	{
		return ['NextrasTests\Orm\ManyHasManyTestEntity'];
	}
}


class AnnotationParserParseOneHasManyTest extends TestCase
{

	public function testOneHasMany()
	{
		$dependencies = [];
		$parser = new AnnotationParser([
			'NextrasTests\Orm\Entity\Reflection\Bar' => 'NextrasTests\Orm\Entity\Reflection\BarRepository',
		]);

		$metadata = $parser->parseMetadata('NextrasTests\Orm\Entity\Reflection\OneHasManyTestEntity', $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same('NextrasTests\Orm\Entity\Reflection\BarRepository', $propertyMeta->relationship->repository);
		Assert::same('oneHasManyTestEntity', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('NextrasTests\Orm\Entity\Reflection\BarRepository', $propertyMeta->relationship->repository);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		// testing order

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same('NextrasTests\Orm\Entity\Reflection\BarRepository', $propertyMeta->relationship->repository);
		Assert::same('oneHasManyTestEntity', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id', ICollection::ASC], $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same('NextrasTests\Orm\Entity\Reflection\BarRepository', $propertyMeta->relationship->repository);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::DESC], $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		// testing new syntax

		$propertyMeta = $metadata->getProperty('test13');
		Assert::same('NextrasTests\Orm\Entity\Reflection\BarRepository', $propertyMeta->relationship->repository);
		Assert::same('oneHasManyTestEntity', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id', ICollection::ASC], $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test14');
		Assert::same('NextrasTests\Orm\Entity\Reflection\BarRepository', $propertyMeta->relationship->repository);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::DESC], $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);
	}

}


$test = new AnnotationParserParseOneHasManyTest($dic);
$test->run();

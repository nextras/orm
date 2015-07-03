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
 * @property mixed $test1 {m:m FooRepository}
 * @property mixed $test2 {m:m FooRepository primary}
 * @property mixed $test3 {m:m FooRepository $property}
 * @property mixed $test4 {m:m FooRepository $property primary}
 *
 * @property mixed $test5 {m:m FooRepository order:this->entity->id}
 * @property mixed $test6 {m:m FooRepository primary order:id,DESC}
 * @property mixed $test7 {m:m FooRepository $property order:id}
 * @property mixed $test8 {m:m FooRepository $property primary order:id}
 *
 * @property mixed $test15 {m:m Foo order:this->entity->id}
 * @property mixed $test16 {m:m Foo primary order:id,DESC}
 * @property mixed $test17 {m:m Foo::$property order:id}
 * @property mixed $test18 {m:m Foo::$property primary order:id}
 */
class ManyHasManyTestEntity
{}


class FooRepository extends Repository
{
	public static function getEntityClassNames()
	{
		return ['NextrasTests\Orm\ManyHasManyTestEntity'];
	}
}


class AnnotationParserParseManyHasManyTest extends TestCase
{

	public function testManyHasMany()
	{
		$dependencies = [];
		$parser = new AnnotationParser([
			'NextrasTests\Orm\Entity\Reflection\Foo' => 'NextrasTests\Orm\Entity\Reflection\FooRepository',
		]);

		$metadata = $parser->parseMetadata('NextrasTests\Orm\Entity\Reflection\ManyHasManyTestEntity', $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::MANY_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		// testing order

		$propertyMeta = $metadata->getProperty('test5');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id', ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test6');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::DESC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test7');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test8');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::ASC], $propertyMeta->relationship->order);

		// testing new syntax

		$propertyMeta = $metadata->getProperty('test15');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id', ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test16');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::DESC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test17');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test18');
		Assert::same('NextrasTests\Orm\Entity\Reflection\FooRepository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::ASC], $propertyMeta->relationship->order);
	}

}


$test = new AnnotationParserParseManyHasManyTest($dic);
$test->run();

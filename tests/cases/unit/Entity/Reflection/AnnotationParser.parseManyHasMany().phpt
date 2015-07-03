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
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property mixed $test1 {m:m \Repository}
 * @property mixed $test2 {m:m \Repository primary}
 * @property mixed $test3 {m:m \Repository $property}
 * @property mixed $test4 {m:m \Repository $property primary}
 *
 * @property mixed $test5 {m:m \Repository order:this->entity->id}
 * @property mixed $test6 {m:m \Repository primary order:id,DESC}
 * @property mixed $test7 {m:m \Repository $property order:id}
 * @property mixed $test8 {m:m \Repository $property primary order:id}
 */
class ManyHasManyTestEntity
{}


class AnnotationParserParseManyHasManyTest extends TestCase
{

	public function testManyHasMany()
	{
		$dependencies = [];
		$parser = new AnnotationParser();
		$metadata = $parser->parseMetadata('NextrasTests\Orm\Entity\Reflection\ManyHasManyTestEntity', $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::MANY_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		// testing order

		$propertyMeta = $metadata->getProperty('test5');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id', ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test6');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::DESC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test7');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(FALSE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test8');
		Assert::same('Repository', $propertyMeta->relationship->repository);
		Assert::same(TRUE, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::ASC], $propertyMeta->relationship->order);
	}

}


$test = new AnnotationParserParseManyHasManyTest($dic);
$test->run();

<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Tests\TestCase;
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
		$metadata = $parser->parseMetadata('Nextras\Orm\Tests\Entity\Reflection\ManyHasManyTestEntity', $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(FALSE, $propertyMeta->relationshipIsMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationshipProperty);
		Assert::same(FALSE, isset($propertyMeta->args->relationship));
		Assert::same(PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY, $propertyMeta->relationshipType);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(TRUE, $propertyMeta->relationshipIsMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationshipProperty);
		Assert::same(FALSE, isset($propertyMeta->args->relationship));

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(FALSE, $propertyMeta->relationshipIsMain);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(FALSE, isset($propertyMeta->args->relationship));

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(TRUE, $propertyMeta->relationshipIsMain);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(FALSE, isset($propertyMeta->args->relationship));

		// testing order

		$propertyMeta = $metadata->getProperty('test5');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(FALSE, $propertyMeta->relationshipIsMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationshipProperty);
		Assert::same(['order' => ['this->entity->id', ICollection::ASC]], $propertyMeta->args->relationship);

		$propertyMeta = $metadata->getProperty('test6');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(TRUE, $propertyMeta->relationshipIsMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationshipProperty);
		Assert::same(['order' => ['id', ICollection::DESC]], $propertyMeta->args->relationship);

		$propertyMeta = $metadata->getProperty('test7');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(FALSE, $propertyMeta->relationshipIsMain);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(['order' => ['id', ICollection::ASC]], $propertyMeta->args->relationship);

		$propertyMeta = $metadata->getProperty('test8');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(TRUE, $propertyMeta->relationshipIsMain);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(['order' => ['id', ICollection::ASC]], $propertyMeta->args->relationship);
	}

}


$test = new AnnotationParserParseManyHasManyTest($dic);
$test->run();

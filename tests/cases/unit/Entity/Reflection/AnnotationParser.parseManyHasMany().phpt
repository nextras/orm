<?php

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
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
 */
class ManyHasManyTestEntity
{}


/**
 * @testCase
 */
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
		Assert::same(PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY, $propertyMeta->relationshipType);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(TRUE, $propertyMeta->relationshipIsMain);
		Assert::same('manyHasManyTestEntities', $propertyMeta->relationshipProperty);

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(FALSE, $propertyMeta->relationshipIsMain);
		Assert::same('property', $propertyMeta->relationshipProperty);

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same(TRUE, $propertyMeta->relationshipIsMain);
		Assert::same('property', $propertyMeta->relationshipProperty);
	}

}


$test = new AnnotationParserParseManyHasManyTest($dic);
$test->run();

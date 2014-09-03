<?php

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property mixed $test1 {1:m \Repository}
 * @property mixed $test2 {1:n \Repository $property}
 */
class OneHasManyTestEntity
{}


/**
 * @testCase
 */
class AnnotationParserParseOneHasManyTest extends TestCase
{

	public function testOneHasMany()
	{
		$dependencies = [];
		$parser = new AnnotationParser();
		$metadata = $parser->parseMetadata('Nextras\Orm\Tests\Entity\Reflection\OneHasManyTestEntity', $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same('oneHasManyTestEntity', $propertyMeta->relationshipProperty);
		Assert::same(PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY, $propertyMeta->relationshipType);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY, $propertyMeta->relationshipType);
	}

}


$test = new AnnotationParserParseOneHasManyTest($dic);
$test->run();

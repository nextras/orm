<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property mixed $test1 {1:m \Repository}
 * @property mixed $test2 {1:n \Repository $property}
 *
 * @property mixed $test3 {1:m \Repository order:this->entity->id}
 * @property mixed $test4 {1:n \Repository $property order:id,DESC}
 */
class OneHasManyTestEntity
{}


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
		Assert::same(FALSE, isset($propertyMeta->args->relationship));
		Assert::same(PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY, $propertyMeta->relationshipType);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(FALSE, isset($propertyMeta->args->relationship));
		Assert::same(PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY, $propertyMeta->relationshipType);

		// testing order

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same('oneHasManyTestEntity', $propertyMeta->relationshipProperty);
		Assert::same(['order' => ['this->entity->id', ICollection::ASC]], $propertyMeta->args->relationship);
		Assert::same(PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY, $propertyMeta->relationshipType);

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same('Repository', $propertyMeta->relationshipRepository);
		Assert::same('property', $propertyMeta->relationshipProperty);
		Assert::same(['order' => ['id', ICollection::DESC]], $propertyMeta->args->relationship);
		Assert::same(PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY, $propertyMeta->relationshipType);
	}

}


$test = new AnnotationParserParseOneHasManyTest($dic);
$test->run();

<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Repository\Repository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property mixed $test1 {1:n Bar::$property}
 * @property mixed $test2 {1:m Bar::$property, orderBy=this->entity->id}
 * @property mixed $test3 {1:n Bar::$property, orderBy=[id,DESC]}
 */
class OneHasManyTestEntity
{}


class BarRepository extends Repository
{
	public static function getEntityClassNames()
	{
		return [ManyHasManyTestEntity::class];
	}
}


class AnnotationParserParseOneHasManyTest extends TestCase
{

	public function testOneHasMany()
	{
		$dependencies = [];
		$parser = new MetadataParser([
			Bar::class => BarRepository::class,
		]);

		$metadata = $parser->parseMetadata(OneHasManyTestEntity::class, $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same(BarRepository::class, $propertyMeta->relationship->repository);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same(BarRepository::class, $propertyMeta->relationship->repository);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id', ICollection::ASC], $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same(BarRepository::class, $propertyMeta->relationship->repository);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id', ICollection::DESC], $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::ONE_HAS_MANY, $propertyMeta->relationship->type);
	}

}


$test = new AnnotationParserParseOneHasManyTest($dic);
$test->run();

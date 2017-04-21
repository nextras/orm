<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Repository\Repository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $id {primary}
 * @property mixed $test1 {m:m Foo::$property}
 * @property mixed $test2 {m:m Foo::$property, isMain=true}
 * @property mixed $test3 {m:m Foo::$property, orderBy=this->entity->id}
 * @property mixed $test4 {m:m Foo::$property, isMain=true, orderBy=[id, DESC]}
 * @property mixed $test5 {m:m Foo::$property, orderBy=id}
 * @property mixed $test6 {m:m Foo::$property, isMain=true, orderBy=id}
 * @property mixed $test7 {m:m Foo::$property, orderBy=[id=ASC, name=DESC]}
 */
class ManyHasManyTestEntity extends Entity
{}


class FooRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [ManyHasManyTestEntity::class];
	}
}


class MetadataParserParseManyHasManyTest extends TestCase
{
	public function testManyHasMany()
	{
		$dependencies = [];
		$parser = new MetadataParser([
			Foo::class => FooRepository::class,
		]);

		$metadata = $parser->parseMetadata(ManyHasManyTestEntity::class, $dependencies);

		/** @var PropertyMetadata $propertyMeta */
		$propertyMeta = $metadata->getProperty('test1');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(false, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);
		Assert::same(PropertyRelationshipMetadata::MANY_HAS_MANY, $propertyMeta->relationship->type);

		$propertyMeta = $metadata->getProperty('test2');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(true, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(NULL, $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test3');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(false, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['this->entity->id' => ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test4');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(true, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id' => ICollection::DESC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test5');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(false, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id' => ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test6');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(true, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id' => ICollection::ASC], $propertyMeta->relationship->order);

		$propertyMeta = $metadata->getProperty('test7');
		Assert::same(FooRepository::class, $propertyMeta->relationship->repository);
		Assert::same(false, $propertyMeta->relationship->isMain);
		Assert::same('property', $propertyMeta->relationship->property);
		Assert::same(['id' => ICollection::ASC, 'name' => ICollection::DESC], $propertyMeta->relationship->order);
	}
}


$test = new MetadataParserParseManyHasManyTest($dic);
$test->run();

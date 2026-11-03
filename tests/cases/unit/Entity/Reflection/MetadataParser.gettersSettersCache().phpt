<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;


use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int    $id   {primary}
 * @property string $name
 */
abstract class SharedBaseEntity extends Entity
{
}


class GetterChildEntity extends SharedBaseEntity
{
	protected function getterName(): string
	{
		return 'getter';
	}
}


class SetterChildEntity extends SharedBaseEntity
{
	protected function setterName(string $value): string
	{
		return $value;
	}
}


class PlainChildEntity extends SharedBaseEntity
{
}


class MetadataParserGettersSettersCacheTest extends TestCase
{
	/**
	 * Entities that share a defining class/trait share a parser cache entry for its properties.
	 * Getters/setters depend on the concrete entity's method list and therefore must be resolved
	 * per entity — the resolution for the first parsed entity must not leak into its siblings.
	 */
	public function testGettersSettersResolvedPerEntity(): void
	{
		$dependencies = [];
		$parser = new MetadataParser([]);

		// parse the entity WITH a getter first, so a shared cache would bake it in
		$getterMeta = $parser->parseMetadata(GetterChildEntity::class, $dependencies);
		$setterMeta = $parser->parseMetadata(SetterChildEntity::class, $dependencies);
		$plainMeta = $parser->parseMetadata(PlainChildEntity::class, $dependencies);

		Assert::same('gettername', $getterMeta->getProperty('name')->hasGetter);
		Assert::null($getterMeta->getProperty('name')->hasSetter);

		Assert::null($setterMeta->getProperty('name')->hasGetter);
		Assert::same('settername', $setterMeta->getProperty('name')->hasSetter);

		// the plain entity must not inherit the sibling's getter/setter
		Assert::null($plainMeta->getProperty('name')->hasGetter);
		Assert::null($plainMeta->getProperty('name')->hasSetter);

		// sibling entities must not share the same PropertyMetadata instance
		Assert::notSame($getterMeta->getProperty('name'), $plainMeta->getProperty('name'));
	}


	/**
	 * The same must hold regardless of parse order (plain entity parsed first).
	 */
	public function testGettersSettersResolvedPerEntityReversedOrder(): void
	{
		$dependencies = [];
		$parser = new MetadataParser([]);

		$plainMeta = $parser->parseMetadata(PlainChildEntity::class, $dependencies);
		$getterMeta = $parser->parseMetadata(GetterChildEntity::class, $dependencies);

		Assert::null($plainMeta->getProperty('name')->hasGetter);
		Assert::same('gettername', $getterMeta->getProperty('name')->hasGetter);
	}
}


$test = new MetadataParserGettersSettersCacheTest();
$test->run();

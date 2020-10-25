<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;


use Nextras\Orm\Entity\Reflection\InvalidModifierDefinitionException;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Exception\InvalidStateException;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @author Foo
 */
class EdgeCasesMetadataParserEntity1
{
}


/**
 * @property string $var {m:1 ]}
 */
class EdgeCasesMetadataParserEntity4
{
}


/**
 * @property string $var {unknown}
 */
class EdgeCasesMetadataParserEntity5
{
}


/**
 * @property foo $var {1:m}
 */
class EdgeCasesMetadataParserEntity6
{
}


/**
 * @property foo $var {1:m Entity}
 */
class EdgeCasesMetadataParserEntity7
{
}


/**
 * @property foo $var {1:m Entity::$bar}
 */
class EdgeCasesMetadataParserEntity8
{
}


/**
 * @property foo $var {primary is_primary, my_order=[foo, bar]}
 */
class EdgeCasesMetadataParserEntity9
{
}


/**
 * @property foo $var {1:m Entity:$bar}
 */
class EdgeCasesMetadataParserEntity10
{
}


class MetadataParserExceptionsTest extends TestCase
{
	public function testOneHasMany(): void
	{
		$parser = new MetadataParser([]);

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity1::class, $dep);
		}, InvalidStateException::class); // missing primary modifier

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity4::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Invalid modifier definition for NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity4::$var property.');

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity5::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Unknown modifier \'unknown\' type for NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity5::$var property.');

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity6::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Relationship {1:m} in NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity6::$var has not defined target entity and its property name.');
		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity7::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Relationship {1:m} in NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity7::$var has not defined target property name.');
		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity8::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Relationship {1:m} in NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity8::$var points to unknown \'NextrasTests\Orm\Entity\Reflection\Entity\' entity. Don\'t forget to return it in IRepository::getEntityClassNames() and register its repository.');
	}


	public function testWrongArguments(): void
	{
		$parser = new MetadataParser([]);

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity9::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Modifier {primary} in NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity9::$var property has unknown arguments: is_primary, my_order.');
		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(EdgeCasesMetadataParserEntity10::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Relationship {1:m} in NextrasTests\Orm\Entity\Reflection\EdgeCasesMetadataParserEntity10::$var has invalid class name of the target entity. Use Entity::$property format.');
	}
}


$test = new MetadataParserExceptionsTest();
$test->run();

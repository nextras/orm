<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;


use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\Reflection\InvalidModifierDefinitionException;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $id {primary}
 * @property type $var {wrapper OkPropertyWrapper}
 */
class ParseContainerEntity1 extends Entity
{
}


/**
 * @property int $id {primary}
 * @property type $var {wrapper WrongPropertyWrapper}
 */
class ParseContainerEntity2 extends Entity
{
}


/**
 * @property int $id {primary}
 * @property type $var {wrapper UnknownPropertyWrapper}
 */
class ParseContainerEntity3 extends Entity
{
}


class OkPropertyWrapper implements IProperty
{
	/** @phpstan-ignore-next-line */
	public function __construct(PropertyMetadata $propertyMetadata)
	{
	}


	public function convertToRawValue($value)
	{
		return $value;
	}


	public function setRawValue($value): void
	{
	}


	public function getRawValue()
	{
		return null;
	}
}


class WrongPropertyWrapper
{
}


class MetadataParserParseContainerTest extends TestCase
{
	public function testContainer(): void
	{
		$parser = new MetadataParser([]);
		$metadata = $parser->parseMetadata(ParseContainerEntity1::class, $dep);
		Assert::same(OkPropertyWrapper::class, $metadata->getProperty('var')->wrapper);

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(ParseContainerEntity2::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Class \'NextrasTests\Orm\Entity\Reflection\WrongPropertyWrapper\' in {wrapper} for NextrasTests\Orm\Entity\Reflection\ParseContainerEntity2::$var property does not implement Nextras\Orm\Entity\IProperty interface.');

		Assert::throws(function () use ($parser): void {
			$parser->parseMetadata(ParseContainerEntity3::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Class \'NextrasTests\Orm\Entity\Reflection\UnknownPropertyWrapper\' in {wrapper} for NextrasTests\Orm\Entity\Reflection\ParseContainerEntity3::$var property does not exist.');
	}
}


$test = new MetadataParserParseContainerTest($dic);
$test->run();

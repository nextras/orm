<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidModifierDefinitionException;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property type $var {container OkContainer}
 */
class ParseContainerEntity1
{}
/**
 * @property type $var {container WrongContainer}
 */
class ParseContainerEntity2
{}
/**
 * @property type $var {container UnknownContainer}
 */
class ParseContainerEntity3
{}


class OkContainer implements IProperty
{
	public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata) {}
	public function setRawValue($value) {}
	public function getRawValue() {}
}
class WrongContainer
{}


class MetadataParserParseContainerTest extends TestCase
{
	public function testOneHasMany()
	{
		$parser = new MetadataParser([]);

		$metadata = $parser->parseMetadata(ParseContainerEntity1::class, $dep);
		Assert::same(OkContainer::class, $metadata->getProperty('var')->container);

		Assert::throws(function () use ($parser) {
			$parser->parseMetadata(ParseContainerEntity2::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Class \'NextrasTests\Orm\Entity\Reflection\WrongContainer\' in {container} for NextrasTests\Orm\Entity\Reflection\ParseContainerEntity2::$var property does not implement Nextras\Orm\Entity\IProperty interface.');

		Assert::throws(function () use ($parser) {
			$parser->parseMetadata(ParseContainerEntity3::class, $dep);
		}, InvalidModifierDefinitionException::class, 'Class \'NextrasTests\Orm\Entity\Reflection\UnknownContainer\' in {container} for NextrasTests\Orm\Entity\Reflection\ParseContainerEntity3::$var property does not exist.');
	}
}


$test = new MetadataParserParseContainerTest($dic);
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $id {primary}
 * @property int $test1 {enum EnumTestEntity::TYPE_ONE}
 * @property int $test2 {enum EnumTestEntity::TYPE_ONE, EnumTestEntity::TYPES_THREE}
 * @property int $test3 {enum EnumTestEntity::TYPE_*,}
 * @property int $test4 {enum EnumTestEntity::TYPES_*, EnumTestEntity::TYPE_ONE}
 * @property int $test5 {enum EnumTestEntity::TYPES_*, EnumTestEntity::TYPE_*}
 * @property int $test6 {enum self::TYPE_*}
 * @property int $test7 {enum static::TYPES_THREE}
 * @property int $test8 {enum \NextrasTests\Orm\Entity\Reflection\EnumTestEntity::TYPE_*}
 * @property string $test9 {enum Enum::A, Enum::B}
 * @property string $test10 {enum Enum::*}
 */
class EnumTestEntity extends Entity
{
	const TYPE_ONE = 1;
	const TYPE_TWO = 2;

	const TYPES_THREE = 3;
	const TYPES_FOUR = 4;
}

class Enum
{
	const A = 'a';
	const B = 'b';
}

class MetadataParserParseEnumTest extends TestCase
{
	public function testBasics()
	{
		$dependencies = [];
		$parser = new MetadataParser([]);
		$metadata = $parser->parseMetadata(EnumTestEntity::class, $dependencies);

		Assert::same([1], $metadata->getProperty('test1')->enum);
		Assert::same([1, 3], $metadata->getProperty('test2')->enum);
		Assert::same([1, 2], $metadata->getProperty('test3')->enum);
		Assert::same([3, 4, 1], $metadata->getProperty('test4')->enum);
		Assert::same([3, 4, 1, 2], $metadata->getProperty('test5')->enum);
		Assert::same([1, 2], $metadata->getProperty('test6')->enum);
		Assert::same([3], $metadata->getProperty('test7')->enum);
		Assert::same([1, 2], $metadata->getProperty('test8')->enum);
		Assert::same(['a', 'b'], $metadata->getProperty('test9')->enum);
		Assert::same(['a', 'b'], $metadata->getProperty('test10')->enum);
	}
}


$test = new MetadataParserParseEnumTest($dic);
$test->run();

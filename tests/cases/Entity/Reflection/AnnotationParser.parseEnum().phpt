<?php

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nette\Utils\DateTime;
use Nextras\Orm\Entity\PropertyContainers\DateTimePropertyContainer;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @property int $test1 {enum EnumTestEntity::TYPE_ONE}
 * @property int $test2 {enum EnumTestEntity::TYPE_ONE EnumTestEntity::TYPES_THREE}
 * @property int $test3 {enum EnumTestEntity::TYPE_*}
 * @property int $test4 {enum EnumTestEntity::TYPES_* EnumTestEntity::TYPE_ONE}
 * @property int $test5 {enum EnumTestEntity::TYPES_* EnumTestEntity::TYPE_*}
 * @property int $test6 {enum self::TYPE_*}
 * @property int $test7 {enum static::TYPES_THREE}
 * @property int $test8 {enum \Nextras\Orm\Tests\Entity\Reflection\EnumTestEntity::TYPE_*}
 * @property string $test9 {enum Enum::A Enum::B}
 */
class EnumTestEntity
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


/**
 * @testCase
 */
class AnnotationParserParseEnumTest extends TestCase
{

	public function testBasics()
	{
		$dp = [];
		$parser = new AnnotationParser('Nextras\Orm\Tests\Entity\Reflection\EnumTestEntity');
		$metadata = $parser->getMetadata($dp);

		Assert::same([1], $metadata->getProperty('test1')->enum);
		Assert::same([1, 3], $metadata->getProperty('test2')->enum);
		Assert::same([1, 2], $metadata->getProperty('test3')->enum);
		Assert::same([3, 4, 1], $metadata->getProperty('test4')->enum);
		Assert::same([3, 4, 1, 2], $metadata->getProperty('test5')->enum);
		Assert::same([1, 2], $metadata->getProperty('test6')->enum);
		Assert::same([3], $metadata->getProperty('test7')->enum);
		Assert::same([1, 2], $metadata->getProperty('test8')->enum);
		Assert::same(['a', 'b'], $metadata->getProperty('test9')->enum);
	}

}


$test = new AnnotationParserParseEnumTest($dic);
$test->run();

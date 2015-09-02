<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $test1 {default 0}
 * @property int $test2 {default true}
 * @property int $test3 {default false}
 * @property int $test4 {default null}
 * @property int $test5 {default self::DEF_VALUE_1}
 * @property int $test6 {default static::DEF_VALUE_2}
 * @property int $test7 {default DefaultTestEntity::DEF_VALUE_1}
 * @property int $test8 {default ''}
 * @property int $test9 {default 'lorem \' ipsum " dolor \\ sit amet'}
 * @property int $test10 {default "lorem ' ipsum \" dolor \\ sit amet"}
 */
class DefaultTestEntity
{
	const DEF_VALUE_1 = 1;
	const DEF_VALUE_2 = NULL;
}

/**
 * @property int $test {default self::UNKNWON}
 */
class DefaultUnknown
{
}


class AnnotationParserParseDefaultTest extends TestCase
{

	public function testBasics()
	{
		$dependencies = [];
		$parser = new AnnotationParser([]);
		$metadata = $parser->parseMetadata('NextrasTests\Orm\Entity\Reflection\DefaultTestEntity', $dependencies);

		Assert::same('0', $metadata->getProperty('test1')->defaultValue);
		Assert::same(TRUE, $metadata->getProperty('test2')->defaultValue);
		Assert::same(FALSE, $metadata->getProperty('test3')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test4')->defaultValue);
		Assert::same(1, $metadata->getProperty('test5')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test6')->defaultValue);
		Assert::same(1, $metadata->getProperty('test7')->defaultValue);
		Assert::same('', $metadata->getProperty('test8')->defaultValue);
		Assert::same('lorem \' ipsum " dolor \\ sit amet', $metadata->getProperty('test9')->defaultValue);
		Assert::same('lorem \' ipsum " dolor \\ sit amet', $metadata->getProperty('test10')->defaultValue);
	}


	public function testUnknown()
	{
		Assert::throws(function () {
			$dependencies = [];
			$parser = new AnnotationParser([]);
			$parser->parseMetadata('NextrasTests\Orm\Entity\Reflection\DefaultUnknown', $dependencies);
		}, 'Nextras\Orm\InvalidArgumentException', 'Constant NextrasTests\Orm\Entity\Reflection\DefaultUnknown::UNKNWON required by default macro in NextrasTests\Orm\Entity\Reflection\DefaultUnknown::$test not found.');
	}

}


$test = new AnnotationParserParseDefaultTest($dic);
$test->run();

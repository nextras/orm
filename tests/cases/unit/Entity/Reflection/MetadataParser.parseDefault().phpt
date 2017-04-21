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
class DefaultTestEntity extends Entity
{
	const DEF_VALUE_1 = 1;
	const DEF_VALUE_2 = NULL;
}


class MetadataParserParseDefaultTest extends TestCase
{
	public function testBasics()
	{
		$dependencies = [];
		$parser = new MetadataParser([]);
		$metadata = $parser->parseMetadata(DefaultTestEntity::class, $dependencies);

		Assert::same(0, $metadata->getProperty('test1')->defaultValue);
		Assert::same(true, $metadata->getProperty('test2')->defaultValue);
		Assert::same(false, $metadata->getProperty('test3')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test4')->defaultValue);
		Assert::same(1, $metadata->getProperty('test5')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test6')->defaultValue);
		Assert::same(1, $metadata->getProperty('test7')->defaultValue);
		Assert::same('', $metadata->getProperty('test8')->defaultValue);
		Assert::same('lorem \' ipsum " dolor \\ sit amet', $metadata->getProperty('test9')->defaultValue);
		Assert::same('lorem \' ipsum " dolor \\ sit amet', $metadata->getProperty('test10')->defaultValue);
	}
}


$test = new MetadataParserParseDefaultTest($dic);
$test->run();

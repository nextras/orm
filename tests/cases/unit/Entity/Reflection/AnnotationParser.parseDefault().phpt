<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $test1 {default 0}
 * @property int $test2 {default true}
 * @property int $test3 {default false}
 * @property int $test4 {default null}
 */
class EnumTestEntity
{
}


class AnnotationParserParseDefaultTest extends TestCase
{

	public function testBasics()
	{
		$dependencies = [];
		$parser = new AnnotationParser();
		$metadata = $parser->parseMetadata('Nextras\Orm\Tests\Entity\Reflection\EnumTestEntity', $dependencies);

		Assert::same('0', $metadata->getProperty('test1')->defaultValue);
		Assert::same(TRUE, $metadata->getProperty('test2')->defaultValue);
		Assert::same(FALSE, $metadata->getProperty('test3')->defaultValue);
		Assert::same(NULL, $metadata->getProperty('test4')->defaultValue);
	}

}


$test = new AnnotationParserParseDefaultTest($dic);
$test->run();

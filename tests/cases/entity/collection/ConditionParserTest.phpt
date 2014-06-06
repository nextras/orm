<?php

namespace Nextras\Orm\Tests\Entity\Collection;

use ArrayIterator;
use Mockery;
use Nextras\Orm\Entity\Collection\Collection;
use Nextras\Orm\Entity\Collection\ConditionParser;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class ConditionParserTest extends TestCase
{

	public function testParse()
	{
		Assert::same(['column'], ConditionParser::parseCondition('column'));
		Assert::same(['column', 'name'], ConditionParser::parseCondition('this->column->name'));
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParser::parseCondition('column->name');
		}, 'Nextras\Orm\InvalidArgumentException');

		Assert::throws(function () {
			ConditionParser::parseCondition('this->property.column');
		}, 'Nextras\Orm\InvalidArgumentException');

		Assert::throws(function () {
			ConditionParser::parseCondition('column.name');
		}, 'Nextras\Orm\InvalidArgumentException');
	}

}


$test = new ConditionParserTest;
$test->run();

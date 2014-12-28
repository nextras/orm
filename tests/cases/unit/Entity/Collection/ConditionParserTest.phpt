<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Collection;

use Mockery;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class ConditionParserTest extends TestCase
{

	public function testParse()
	{
		Assert::same([['column'], '='], ConditionParserHelper::parseCondition('column'));
		Assert::same([['column'], '!='], ConditionParserHelper::parseCondition('column!'));
		Assert::same([['column'], '!='], ConditionParserHelper::parseCondition('column!='));
		Assert::same([['column'], '<='], ConditionParserHelper::parseCondition('column<='));
		Assert::same([['column'], '>='], ConditionParserHelper::parseCondition('column>='));
		Assert::same([['column'], '>'], ConditionParserHelper::parseCondition('column>'));
		Assert::same([['column'], '<'], ConditionParserHelper::parseCondition('column<'));
		Assert::same([['column', 'name'], '='], ConditionParserHelper::parseCondition('this->column->name'));
		Assert::same([['column', 'name'], '!='], ConditionParserHelper::parseCondition('this->column->name!'));
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParserHelper::parseCondition('column->name');
		}, 'Nextras\Orm\InvalidArgumentException');

		Assert::throws(function () {
			ConditionParserHelper::parseCondition('this->property.column');
		}, 'Nextras\Orm\InvalidArgumentException');

		Assert::throws(function () {
			ConditionParserHelper::parseCondition('column.name');
		}, 'Nextras\Orm\InvalidArgumentException');
	}

}


$test = new ConditionParserTest($dic);
$test->run();

<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

use Mockery;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class ConditionParserHelperTest extends TestCase
{

	public function testParse()
	{
		Assert::same([['column'], '=', NULL], ConditionParserHelper::parseCondition('column'));
		Assert::same([['column'], '!=', NULL], ConditionParserHelper::parseCondition('column!'));
		Assert::same([['column'], '!=', NULL], ConditionParserHelper::parseCondition('column!='));
		Assert::same([['column'], '<=', NULL], ConditionParserHelper::parseCondition('column<='));
		Assert::same([['column'], '>=', NULL], ConditionParserHelper::parseCondition('column>='));
		Assert::same([['column'], '>', NULL], ConditionParserHelper::parseCondition('column>'));
		Assert::same([['column'], '<', NULL], ConditionParserHelper::parseCondition('column<'));

		Assert::same([['column', 'name'], '=', NULL], ConditionParserHelper::parseCondition('this->column->name'));
		Assert::same([['column', 'name'], '!=', NULL], ConditionParserHelper::parseCondition('this->column->name!'));

		Assert::same([['column', 'name'], '=', 'Book'], ConditionParserHelper::parseCondition('Book->column->name'));
		Assert::same([['column'], '=', 'NextrasTests\Orm\Book'], ConditionParserHelper::parseCondition('NextrasTests\Orm\Book->column'));
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParserHelper::parseCondition('this->property.column');
		}, 'Nextras\Orm\InvalidArgumentException');

		Assert::throws(function () {
			ConditionParserHelper::parseCondition('column.name');
		}, 'Nextras\Orm\InvalidArgumentException');
	}

}


$test = new ConditionParserHelperTest($dic);
$test->run();

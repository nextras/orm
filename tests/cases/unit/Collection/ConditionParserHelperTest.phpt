<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

use Mockery;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class ConditionParserHelperTest extends TestCase
{

	public function testParse()
	{
		Assert::same([['column'], '=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column'));
		Assert::same([['column'], '!=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column!'));
		Assert::same([['column'], '!=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column!='));
		Assert::same([['column'], '<=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column<='));
		Assert::same([['column'], '>=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column>='));
		Assert::same([['column'], '>', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column>'));
		Assert::same([['column'], '<', NULL], ConditionParserHelper::parsePropertyExprWithOperator('column<'));

		Assert::same([['column', 'name'], '=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('this->column->name'));
		Assert::same([['column', 'name'], '!=', NULL], ConditionParserHelper::parsePropertyExprWithOperator('this->column->name!'));

		Assert::same([['column', 'name'], '=', 'Book'], ConditionParserHelper::parsePropertyExprWithOperator('Book->column->name'));
		Assert::same([['column'], '=', Book::class], ConditionParserHelper::parsePropertyExprWithOperator('NextrasTests\Orm\Book->column'));
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExprWithOperator('this->property.column');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExprWithOperator('column.name');
		}, InvalidArgumentException::class);
	}

}


$test = new ConditionParserHelperTest($dic);
$test->run();

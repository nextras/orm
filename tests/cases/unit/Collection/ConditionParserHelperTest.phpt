<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CompareNotEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanFunction;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class ConditionParserHelperTest extends TestCase
{
	public function testParseOperator()
	{
		Assert::same(
			[CompareEqualsFunction::class, 'column'],
			ConditionParser::parsePropertyOperator('column')
		);
		Assert::same(
			[CompareNotEqualsFunction::class, 'column'],
			ConditionParser::parsePropertyOperator('column!=')
		);
		Assert::same(
			[CompareSmallerThanEqualsFunction::class, 'column'],
			ConditionParser::parsePropertyOperator('column<=')
		);
		Assert::same(
			[CompareGreaterThanEqualsFunction::class, 'column'],
			ConditionParser::parsePropertyOperator('column>=')
		);
		Assert::same(
			[CompareGreaterThanFunction::class, 'column'],
			ConditionParser::parsePropertyOperator('column>')
		);
		Assert::same(
			[CompareSmallerThanFunction::class, 'column'],
			ConditionParser::parsePropertyOperator('column<')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'column->name'],
			ConditionParser::parsePropertyOperator('column->name'));
		Assert::same(
			[CompareNotEqualsFunction::class, 'column->name'],
			ConditionParser::parsePropertyOperator('column->name!=')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'this->column->name'],
			ConditionParser::parsePropertyOperator('this->column->name')
		);
		Assert::same(
			[CompareNotEqualsFunction::class, 'this->column->name', ],
			ConditionParser::parsePropertyOperator('this->column->name!=')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'NextrasTests\Orm\Book::column'],
			ConditionParser::parsePropertyOperator('NextrasTests\Orm\Book::column')
		);
	}


	public function testParseExpression()
	{
		Assert::same([['column'], null], ConditionParser::parsePropertyExpr('column'));
		Assert::same([['column', 'name'], null], ConditionParser::parsePropertyExpr('column->name'));
		Assert::same([['Book', 'column'], null], ConditionParser::parsePropertyExpr('Book->column'));
		Assert::same([
			['column'],
			Book::class,
		], ConditionParser::parsePropertyExpr('NextrasTests\Orm\Book::column'));

		Assert::error(function () {
			Assert::same([
				['column'],
				Book::class,
			], ConditionParser::parsePropertyExpr('NextrasTests\Orm\Book->column'));
		}, E_USER_DEPRECATED, "Using STI class prefix 'NextrasTests\Orm\Book->' is deprecated; use with double-colon 'NextrasTests\Orm\Book::'.");

		Assert::error(function () {
			Assert::same([
				['column', 'name', 'test'],
				null,
			], ConditionParser::parsePropertyExpr('this->column->name->test'));
		}, E_USER_DEPRECATED, "Using 'this->' is deprecated; use property traversing directly without 'this->'.");

		Assert::error(function () {
			Assert::same([['column', 'name'], null], ConditionParser::parsePropertyExpr('this->column->name'));
		}, E_USER_DEPRECATED, "Using 'this->' is deprecated; use property traversing directly without 'this->'.");
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParser::parsePropertyExpr('this->property.column');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParser::parsePropertyExpr('column.name');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParser::parsePropertyExpr('Book::column->name');
		}, InvalidArgumentException::class);
	}
}


$test = new ConditionParserHelperTest($dic);
$test->run();

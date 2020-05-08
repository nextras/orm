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
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
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
			ConditionParserHelper::parsePropertyOperator('column')
		);
		Assert::same(
			[CompareNotEqualsFunction::class, 'column'],
			ConditionParserHelper::parsePropertyOperator('column!=')
		);
		Assert::same(
			[CompareSmallerThanEqualsFunction::class, 'column'],
			ConditionParserHelper::parsePropertyOperator('column<=')
		);
		Assert::same(
			[CompareGreaterThanEqualsFunction::class, 'column'],
			ConditionParserHelper::parsePropertyOperator('column>=')
		);
		Assert::same(
			[CompareGreaterThanFunction::class, 'column'],
			ConditionParserHelper::parsePropertyOperator('column>')
		);
		Assert::same(
			[CompareSmallerThanFunction::class, 'column'],
			ConditionParserHelper::parsePropertyOperator('column<')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'column->name'],
			ConditionParserHelper::parsePropertyOperator('column->name'));
		Assert::same(
			[CompareNotEqualsFunction::class, 'column->name'],
			ConditionParserHelper::parsePropertyOperator('column->name!=')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'this->column->name'],
			ConditionParserHelper::parsePropertyOperator('this->column->name')
		);
		Assert::same(
			[CompareNotEqualsFunction::class, 'this->column->name', ],
			ConditionParserHelper::parsePropertyOperator('this->column->name!=')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'NextrasTests\Orm\Book::column'],
			ConditionParserHelper::parsePropertyOperator('NextrasTests\Orm\Book::column')
		);
	}


	public function testParseExpression()
	{
		Assert::same([['column'], null], ConditionParserHelper::parsePropertyExpr('column'));
		Assert::same([['column', 'name'], null], ConditionParserHelper::parsePropertyExpr('column->name'));
		Assert::same([['Book', 'column'], null], ConditionParserHelper::parsePropertyExpr('Book->column'));
		Assert::same([
			['column'],
			Book::class,
		], ConditionParserHelper::parsePropertyExpr('NextrasTests\Orm\Book::column'));

		Assert::error(function () {
			Assert::same([
				['column'],
				Book::class,
			], ConditionParserHelper::parsePropertyExpr('NextrasTests\Orm\Book->column'));
		}, E_USER_DEPRECATED, "Using STI class prefix 'NextrasTests\Orm\Book->' is deprecated; use with double-colon 'NextrasTests\Orm\Book::'.");

		Assert::error(function () {
			Assert::same([
				['column', 'name', 'test'],
				null,
			], ConditionParserHelper::parsePropertyExpr('this->column->name->test'));
		}, E_USER_DEPRECATED, "Using 'this->' is deprecated; use property traversing directly without 'this->'.");

		Assert::error(function () {
			Assert::same([['column', 'name'], null], ConditionParserHelper::parsePropertyExpr('this->column->name'));
		}, E_USER_DEPRECATED, "Using 'this->' is deprecated; use property traversing directly without 'this->'.");
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExpr('this->property.column');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExpr('column.name');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExpr('Book::column->name');
		}, InvalidArgumentException::class);
	}
}


$test = new ConditionParserHelperTest($dic);
$test->run();

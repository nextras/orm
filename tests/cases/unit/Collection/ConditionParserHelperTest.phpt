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
	public function testParseOperator(): void
	{
		$conditionParser = new ConditionParser();
		Assert::same(
			[CompareEqualsFunction::class, 'column'],
			$conditionParser->parsePropertyOperator('column')
		);
		Assert::same(
			[CompareNotEqualsFunction::class, 'column'],
			$conditionParser->parsePropertyOperator('column!=')
		);
		Assert::same(
			[CompareSmallerThanEqualsFunction::class, 'column'],
			$conditionParser->parsePropertyOperator('column<=')
		);
		Assert::same(
			[CompareGreaterThanEqualsFunction::class, 'column'],
			$conditionParser->parsePropertyOperator('column>=')
		);
		Assert::same(
			[CompareGreaterThanFunction::class, 'column'],
			$conditionParser->parsePropertyOperator('column>')
		);
		Assert::same(
			[CompareSmallerThanFunction::class, 'column'],
			$conditionParser->parsePropertyOperator('column<')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'column->name'],
			$conditionParser->parsePropertyOperator('column->name'));
		Assert::same(
			[CompareNotEqualsFunction::class, 'column->name'],
			$conditionParser->parsePropertyOperator('column->name!=')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'this->column->name'],
			$conditionParser->parsePropertyOperator('this->column->name')
		);
		Assert::same(
			[CompareNotEqualsFunction::class, 'this->column->name', ],
			$conditionParser->parsePropertyOperator('this->column->name!=')
		);
		Assert::same(
			[CompareEqualsFunction::class, 'NextrasTests\Orm\Book::column'],
			$conditionParser->parsePropertyOperator('NextrasTests\Orm\Book::column')
		);
	}


	public function testParseExpression(): void
	{
		$conditionParser = new ConditionParser();

		Assert::same([['column'], null], $conditionParser->parsePropertyExpr('column'));
		Assert::same([['column', 'name'], null], $conditionParser->parsePropertyExpr('column->name'));
		Assert::same([['Book', 'column'], null], $conditionParser->parsePropertyExpr('Book->column'));
		Assert::same([
			['column'],
			Book::class,
		], $conditionParser->parsePropertyExpr('NextrasTests\Orm\Book::column'));

		Assert::error(function () use ($conditionParser): void {
			Assert::same([
				['column'],
				Book::class,
			], $conditionParser->parsePropertyExpr('NextrasTests\Orm\Book->column'));
		}, E_USER_DEPRECATED, "Using STI class prefix 'NextrasTests\Orm\Book->' is deprecated; use with double-colon 'NextrasTests\Orm\Book::'.");

		Assert::error(function () use ($conditionParser): void {
			Assert::same([
				['column', 'name', 'test'],
				null,
			], $conditionParser->parsePropertyExpr('this->column->name->test'));
		}, E_USER_DEPRECATED, "Using 'this->' is deprecated; use property traversing directly without 'this->'.");

		Assert::error(function () use ($conditionParser): void {
			Assert::same([['column', 'name'], null], $conditionParser->parsePropertyExpr('this->column->name'));
		}, E_USER_DEPRECATED, "Using 'this->' is deprecated; use property traversing directly without 'this->'.");
	}


	public function testFailing(): void
	{
		$conditionParser = new ConditionParser();
		Assert::throws(function () use ($conditionParser): void {
			$conditionParser->parsePropertyExpr('this->property.column');
		}, InvalidArgumentException::class);

		Assert::throws(function () use ($conditionParser): void {
			$conditionParser->parsePropertyExpr('column.name');
		}, InvalidArgumentException::class);

		Assert::throws(function () use ($conditionParser): void {
			$conditionParser->parsePropertyExpr('Book::column->name');
		}, InvalidArgumentException::class);
	}
}


$test = new ConditionParserHelperTest($dic);
$test->run();

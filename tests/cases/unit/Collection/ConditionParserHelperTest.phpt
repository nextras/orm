<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

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
		Assert::same(['column', '='], ConditionParserHelper::parsePropertyOperator('column'));
		Assert::same(['column', '!='], ConditionParserHelper::parsePropertyOperator('column!='));
		Assert::same(['column', '<='], ConditionParserHelper::parsePropertyOperator('column<='));
		Assert::same(['column', '>='], ConditionParserHelper::parsePropertyOperator('column>='));
		Assert::same(['column', '>'], ConditionParserHelper::parsePropertyOperator('column>'));
		Assert::same(['column', '<'], ConditionParserHelper::parsePropertyOperator('column<'));

		Assert::same(['this->column->name', '='], ConditionParserHelper::parsePropertyOperator('this->column->name'));
		Assert::same(['this->column->name', '!='], ConditionParserHelper::parsePropertyOperator('this->column->name!='));

		Assert::same(['Book->column->name', '='], ConditionParserHelper::parsePropertyOperator('Book->column->name'));
		Assert::same(['NextrasTests\Orm\Book->column', '='], ConditionParserHelper::parsePropertyOperator('NextrasTests\Orm\Book->column'));
	}


	public function testParseExpression()
	{
		Assert::same([['column'], null], ConditionParserHelper::parsePropertyExpr('column'));
		Assert::same([['column', 'name'], null], ConditionParserHelper::parsePropertyExpr('this->column->name'));
		Assert::same([['column', 'name'], 'Book'], ConditionParserHelper::parsePropertyExpr('Book->column->name'));
		Assert::same([['column'], Book::class], ConditionParserHelper::parsePropertyExpr('NextrasTests\Orm\Book->column'));
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExpr('this->property.column');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParserHelper::parsePropertyExpr('column.name');
		}, InvalidArgumentException::class);
	}
}


$test = new ConditionParserHelperTest($dic);
$test->run();

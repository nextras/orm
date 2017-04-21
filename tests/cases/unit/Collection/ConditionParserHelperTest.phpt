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
		Assert::same([['column'], '=', Book::class], ConditionParserHelper::parseCondition('NextrasTests\Orm\Book->column'));
	}


	public function testFailing()
	{
		Assert::throws(function () {
			ConditionParserHelper::parseCondition('this->property.column');
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			ConditionParserHelper::parseCondition('column.name');
		}, InvalidArgumentException::class);
	}

}


$test = new ConditionParserHelperTest($dic);
$test->run();

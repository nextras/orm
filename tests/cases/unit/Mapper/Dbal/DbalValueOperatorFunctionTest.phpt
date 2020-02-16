<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\CompareFunction;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class DbalValueOperatorFunctionTest extends TestCase
{
	/**
	 * @dataProvider operatorTestProvider
	 */
	public function testOperators($expected, $expr)
	{
		$valueOperatorFunction = new CompareFunction();

		$expressionResult = new DbalExpressionResult(['%column', 'books.id']);

		$helper = Mockery::mock(DbalQueryBuilderHelper::class);
		$helper->shouldReceive('processPropertyExpr')->once()->andReturn($expressionResult);

		$builder = Mockery::mock(QueryBuilder::class);
		$builder->shouldReceive('getFromAlias')->andReturn('books');

		Assert::same(
			$expected,
			$valueOperatorFunction->processQueryBuilderExpression($helper, $builder, $expr)->args
		);
	}


	protected function operatorTestProvider()
	{
		return [
			[['%ex = %any', ['%column', 'books.id'], 1], [ConditionParserHelper::OPERATOR_EQUAL, 'id', 1]],
			[['%ex != %any', ['%column', 'books.id'], 1], [ConditionParserHelper::OPERATOR_NOT_EQUAL, 'id', 1]],
			[['%ex IN %any', ['%column', 'books.id'], [1, 2]], [ConditionParserHelper::OPERATOR_EQUAL, 'id', [1, 2]]],
			[['%ex NOT IN %any', ['%column', 'books.id'], [1, 2]], [ConditionParserHelper::OPERATOR_NOT_EQUAL, 'id', [1, 2]]],
			[['%ex IS NOT NULL', ['%column', 'books.id']], [ConditionParserHelper::OPERATOR_NOT_EQUAL, 'id', null]],
		];
	}
}


(new DbalValueOperatorFunctionTest($dic))->run();

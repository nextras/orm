<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;


use Mockery;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareNotEqualsFunction;
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
	public function testOperators($function, $expected, $expr)
	{
		$expressionResult = new DbalExpressionResult(['%column', 'books.id']);

		$helper = Mockery::mock(DbalQueryBuilderHelper::class);
		$helper->shouldReceive('processPropertyExpr')->once()->andReturn($expressionResult);

		$builder = Mockery::mock(QueryBuilder::class);
		$builder->shouldReceive('getFromAlias')->andReturn('books');

		Assert::same(
			$expected,
			$function->processQueryBuilderExpression($helper, $builder, $expr)->args
		);
	}


	protected function operatorTestProvider()
	{
		return [
			[new CompareEqualsFunction(), ['%ex = %any', ['%column', 'books.id'], 1], ['id', 1]],
			[new CompareNotEqualsFunction(), ['%ex != %any', ['%column', 'books.id'], 1], ['id', 1]],
			[new CompareEqualsFunction(), ['%ex IN %any', ['%column', 'books.id'], [1, 2]], ['id', [1, 2]]],
			[new CompareNotEqualsFunction(), ['%ex NOT IN %any', ['%column', 'books.id'], [1, 2]], ['id', [1, 2]]],
			[new CompareNotEqualsFunction(), ['%ex IS NOT NULL', ['%column', 'books.id']], ['id', null]],
		];
	}
}


(new DbalValueOperatorFunctionTest($dic))->run();

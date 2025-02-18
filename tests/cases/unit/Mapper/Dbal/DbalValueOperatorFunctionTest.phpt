<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;


use Mockery;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\BaseCompareFunction;
use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareNotEqualsFunction;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


class DbalValueOperatorFunctionTest extends TestCase
{
	/**
	 * @dataProvider operatorTestProvider
	 * @param array<mixed> $expected
	 * @param array<mixed> $expr
	 */
	public function testOperators(BaseCompareFunction $function, array $expected, array $expr): void
	{
		$expressionResult = new DbalExpressionResult(expression: '%column', args: ['books.id'], dbalModifier: '%i');

		$helper = Mockery::mock(DbalQueryBuilderHelper::class);
		$helper->shouldReceive('processExpression')->once()->andReturn($expressionResult);

		$builder = Mockery::mock(QueryBuilder::class);
		$builder->shouldReceive('getFromAlias')->andReturn('books');

		Assert::same(
			$expected,
			$function->processDbalExpression($helper, $builder, $expr)->getArgsForExpansion()
		);
	}


	/**
	 * @return array<array{BaseCompareFunction, array<mixed>, array<mixed>}>
	 */
	protected function operatorTestProvider(): array
	{
		return [
			[new CompareEqualsFunction(), ['%column = %i', 'books.id', 1], ['id', 1]],
			[new CompareNotEqualsFunction(), ['%column != %i', 'books.id', 1], ['id', 1]],
			[new CompareEqualsFunction(), ['%column IN %i[]', 'books.id', [1, 2]], ['id', [1, 2]]],
			[new CompareNotEqualsFunction(), ['%column NOT IN %i[]', 'books.id', [1, 2]], ['id', [1, 2]]],
			[new CompareNotEqualsFunction(), ['%column IS NOT NULL', 'books.id'], ['id', null]],
		];
	}
}


$test = new DbalValueOperatorFunctionTest();
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Mapper\Dbal\Helpers\ColumnReference;
use Nextras\Orm\Mapper\Dbal\QueryBuilderHelper;
use Nextras\Orm\Repository\Functions\ValueOperatorFunction;
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
		$valueOperatorFunction = new ValueOperatorFunction();

		$columnReference = Mockery::mock(ColumnReference::class);
		$columnReference->column = 'books.id';

		$helper = Mockery::mock(QueryBuilderHelper::class);
		$helper->shouldReceive('processPropertyExpr')->once()->andReturn($columnReference);
		$helper->shouldReceive('normalizeValue')->once()->with($expr[2], Mockery::any())->andReturn($expr[2]);

		$builder = Mockery::mock(QueryBuilder::class);
		$builder->shouldReceive('getFromAlias')->andReturn('books');

		Assert::same(
			$expected,
			$valueOperatorFunction->processQueryBuilderFilter($helper, $builder, $expr)
		);
	}


	protected function operatorTestProvider()
	{
		return [
			[['%column = %any', 'books.id', 1], [ConditionParserHelper::OPERATOR_EQUAL, 'id', 1]],
			[['%column != %any', 'books.id', 1], [ConditionParserHelper::OPERATOR_NOT_EQUAL, 'id', 1]],
			[['%column IN %any', 'books.id', [1, 2]], [ConditionParserHelper::OPERATOR_EQUAL, 'id', [1, 2]]],
			[['%column NOT IN %any', 'books.id', [1, 2]], [ConditionParserHelper::OPERATOR_NOT_EQUAL, 'id', [1, 2]]],
			[['%column IS NOT NULL', 'books.id'], [ConditionParserHelper::OPERATOR_NOT_EQUAL, 'id', null]],
		];
	}
}


(new DbalValueOperatorFunctionTest($dic))->run();

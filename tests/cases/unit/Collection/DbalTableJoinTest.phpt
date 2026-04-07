<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use Mockery;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class DbalTableJoinTest extends TestCase
{
	public function testApplyJoinUsesHashSuffixForDynamicArgs(): void
	{
		$builder = new QueryBuilder(Mockery::mock(IPlatform::class));
		$builder->from('book', 'b')->select('*');

		$joinA = new DbalTableJoin(
			toExpression: '%table',
			toArgs: ['book_tag'],
			toAlias: 'tag',
			onExpression: '%table.%column = %table.%column',
			onArgs: ['b', 'tag_id', 'tag', 'id'],
		);
		$joinB = new DbalTableJoin(
			toExpression: '%table',
			toArgs: ['author_tag'],
			toAlias: 'tag',
			onExpression: '%table.%column = %table.%column',
			onArgs: ['b', 'tag_id', 'tag', 'id'],
		);

		$joinA->applyJoin($builder);
		$joinB->applyJoin($builder);

		Assert::same(
			[
				'SELECT * FROM book AS [b] '
				. 'LEFT JOIN %table AS [tag] ON (%table.%column = %table.%column) '
				. 'LEFT JOIN %table AS [tag] ON (%table.%column = %table.%column)',
				'book_tag',
				'b',
				'tag_id',
				'tag',
				'id',
				'author_tag',
				'b',
				'tag_id',
				'tag',
				'id',
			],
			[$builder->getQuerySql(), ...$builder->getQueryParameters()],
		);
	}
}


$test = new DbalTableJoinTest();
$test->run();

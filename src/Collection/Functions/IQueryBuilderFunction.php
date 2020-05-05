<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


/**
 * Collection function implementation for DbalCollection.
 * Processes expression and returns Dbal's (expanding) expression.
 */
interface IQueryBuilderFunction
{
	/**
	 * Returns true if entity should stay in the result collection; the condition is evaluated in database and this
	 * method just returns appropriate Nextras Dbal's filtering expression for passed args.
	 * @phpstan-param array<int|string, mixed> $args
	 */
	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): DbalExpressionResult;
}

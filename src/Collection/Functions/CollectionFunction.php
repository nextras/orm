<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;


/**
 * Collection function for custom functionality over array & dbal storage.
 * It processes an expression and reuse nested result for further evaluation.
 * If the collection function does not support particular storage (array or dbal), it may
 * throw an "unsupported" exception.
 */
interface CollectionFunction
{
	/**
	 * Returns a value depending on values of entity; the expression passed by args is evaluated during this method
	 * execution.
	 * Usually returns a boolean for filtering evaluation.
	 * @param array<mixed> $args
	 * @param IArrayAggregator<mixed>|null $aggregator
	 */
	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null,
	): ArrayExpressionResult;


	/**
	 * Returns true if entity should stay in the result collection; the condition is evaluated in database and this
	 * method just returns appropriate Nextras Dbal's filtering expression for passed args.
	 * @param array<int|string, mixed> $args
	 */
	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null,
	): DbalExpressionResult;
}

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\CollectionFunction;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use function assert;
use function count;
use function is_string;
use function str_starts_with;
use function strlen;


class TestingPrefixFunction implements CollectionFunction
{
	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		Aggregator|null $aggregator = null,
	) : DbalExpressionResult
	{
		// $args is for example ['phone', '+420']
		assert(count($args) === 2 && is_string($args[0]) && is_string($args[1]));

		$expression = $helper->processExpression($builder, $args[0], $aggregator);
		return new DbalExpressionResult(
			expression: 'SUBSTRING(%ex, 1, %i) = %s',
			args: [$expression->getArgsForExpansion(), strlen($args[1]), $args[1]],
			joins: $expression->joins,
		);
	}

	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?Aggregator $aggregator = null,
	): ArrayExpressionResult
	{
		// $args is for example ['phone', '+420']
		assert(count($args) === 2 && is_string($args[0]) && is_string($args[1]));

		$valueResult = $helper->getValue($entity, $args[0], $aggregator);
		return new ArrayExpressionResult(
			value: str_starts_with(haystack: $valueResult->value, needle: $args[1]),
		);
	}
}

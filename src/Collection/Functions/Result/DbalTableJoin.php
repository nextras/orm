<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\QueryBuilder\QueryBuilder;


/**
 * SQL join metadata holder.
 *
 * The joins are created lazily and this class holds data for it.
 *
 * Later, to construct an aggregation, the aggregation condition is created over {@see DbalTableJoin::$toPrimaryKey} column.
 * If not needed or possible, pass a null.
 *
 * @experimental
 */
class DbalTableJoin
{
	/**
	 * @param literal-string $toExpression
	 * @param array<mixed> $toArgs
	 * @param literal-string $toAlias
	 * @param literal-string $onExpression
	 * @param array<mixed> $onArgs
	 * @param Fqn|null $toPrimaryKey
	 */
	public function __construct(
		public readonly string $toExpression,
		public readonly array $toArgs,
		public readonly string $toAlias,
		public readonly string $onExpression,
		public readonly array $onArgs,
		public readonly Fqn|null $toPrimaryKey = null,
	)
	{
	}


	public function applyJoin(QueryBuilder $queryBuilder): void
	{
		$queryBuilder->joinLeft(
			"$this->toExpression AS [$this->toAlias]",
			$this->onExpression,
			...$this->toArgs,
			...$this->onArgs,
		);
	}
}

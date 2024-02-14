<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\QueryBuilder\QueryBuilder;


/**
 * SQL join metadata holder.
 *
 * The joins are created lazily and this class holds data for it.
 *
 * If there is an aggregation, the joined table needs to be grouped by {@see DbalTableJoin::$primaryKeys},
 * if not needed or possible, pass jum an empty array.
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
	 * @param list<string> $primaryKeys
	 */
	public function __construct(
		public readonly string $toExpression,
		public readonly array $toArgs,
		public readonly string $toAlias,
		public readonly string $onExpression,
		public readonly array $onArgs,
		public readonly array $primaryKeys = [],
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

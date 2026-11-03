<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use function md5;
use function serialize;


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
	public function __construct(
		/** @var literal-string */
		public readonly string $toExpression,
		/** @var array<mixed> */
		public readonly array $toArgs,
		/** @var literal-string */
		public readonly string $toAlias,
		/** @var literal-string */
		public readonly string $onExpression,
		/** @var array<mixed> */
		public readonly array $onArgs,
		public readonly Fqn|null $toPrimaryKey = null,
	)
	{
	}


	public function applyJoin(QueryBuilder $queryBuilder): void
	{
		$queryBuilder->joinOnce(
			'LEFT',
			"$this->toExpression AS [$this->toAlias]",
			$this->onExpression,
			[
				...$this->toArgs,
				...$this->onArgs,
			],
			hashSuffix: md5(serialize([$this->toArgs, $this->onArgs])),
		);
	}
}

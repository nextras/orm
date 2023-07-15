<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;


/**
 * @experimental
 */
class DbalTableJoin
{
	/** @phpstan-var literal-string */
	public readonly string $toExpression;

	/** @var array<mixed> */
	public readonly array $toArgs;

	/** @phpstan-var literal-string */
	public readonly string $toAlias;

	/** @phpstan-var literal-string */
	public readonly string $onExpression;

	/** @var array<mixed> */
	public readonly array $onArgs;

	public readonly IConventions $conventions;


	/**
	 * @param array<mixed> $toArgs
	 * @param array<mixed> $onArgs
	 * @phpstan-param literal-string $toExpression
	 * @phpstan-param literal-string $toAlias
	 * @phpstan-param literal-string $onExpression
	 */
	public function __construct(
		string $toExpression,
		array $toArgs,
		string $toAlias,
		string $onExpression,
		array $onArgs,
		IConventions $conventions,
		public readonly bool $inner = false,
	)
	{
		$this->toExpression = $toExpression;
		$this->toArgs = $toArgs;
		$this->toAlias = $toAlias;
		$this->onExpression = $onExpression;
		$this->onArgs = $onArgs;
		$this->conventions = $conventions;
	}


	public function withCondition(string $expression, ...$expresionArgs): DbalTableJoin
	{
		return new self(
			toExpression: $this->toExpression,
			toArgs: $this->toArgs,
			toAlias: $this->toAlias,
			onExpression: "$this->onExpression AND $expression",
			onArgs: array_merge($this->onArgs, $expresionArgs),
			conventions: $this->conventions,
			inner: true,
		);
	}


	public function applyJoin(QueryBuilder $queryBuilder): void
	{
		if ($this->inner) {
			$queryBuilder->joinInner(
				"$this->toExpression AS [$this->toAlias]",
				$this->onExpression,
				...$this->toArgs,
				...$this->onArgs,
			);
		} else {
			$queryBuilder->joinLeft(
				"$this->toExpression AS [$this->toAlias]",
				$this->onExpression,
				...$this->toArgs,
				...$this->onArgs,
			);
		}
	}
}

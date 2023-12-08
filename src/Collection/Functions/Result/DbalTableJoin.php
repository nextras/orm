<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;


/**
 * @experimental
 */
class DbalTableJoin
{
	/** @var literal-string */
	public readonly string $toExpression;

	/** @var array<mixed> */
	public readonly array $toArgs;

	/** @var literal-string */
	public readonly string $toAlias;

	/** @var literal-string */
	public readonly string $onExpression;

	/** @var array<mixed> */
	public readonly array $onArgs;

	public readonly IConventions $conventions;


	/**
	 * @param array<mixed> $toArgs
	 * @param array<mixed> $onArgs
	 * @param literal-string $toExpression
	 * @param literal-string $toAlias
	 * @param literal-string $onExpression
	 */
	public function __construct(
		string $toExpression,
		array $toArgs,
		string $toAlias,
		string $onExpression,
		array $onArgs,
		IConventions $conventions,
	)
	{
		$this->toExpression = $toExpression;
		$this->toArgs = $toArgs;
		$this->toAlias = $toAlias;
		$this->onExpression = $onExpression;
		$this->onArgs = $onArgs;
		$this->conventions = $conventions;
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

<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;


/**
 * @experimental
 */
class DbalJoinEntry
{
	/** @var string */
	public $toExpression;

	/** @var string */
	public $alias;

	/** @var string */
	public $onExpression;

	/** @var array<mixed> */
	public $args;

	/** @var IConventions */
	public $conventions;


	/**
	 * @param array<mixed> $args
	 */
	public function __construct(
		string $toExpression,
		string $toAlias,
		string $onExpression,
		array $args,
		IConventions $conventions
	)
	{
		$this->toExpression = $toExpression;
		$this->alias = $toAlias;
		$this->onExpression = $onExpression;
		$this->args = $args;
		$this->conventions = $conventions;
	}


	public function applyJoin(QueryBuilder $queryBuilder): void
	{
		$queryBuilder->joinLeft(
			"{$this->toExpression} AS %table",
			$this->onExpression,
			$this->alias,
			$this->alias, // for %table in onExpression
			...$this->args
		);
	}
}

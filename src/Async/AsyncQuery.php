<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;


/**
 * A not-yet-processed DBAL query: SQL expression with modifiers and its arguments,
 * as accepted by {@see IConnection::queryArgs()}.
 */
final class AsyncQuery
{
	public function __construct(
		public readonly string $sql,
		/** @var list<mixed> */
		public readonly array $args = [],
	)
	{
	}


	public static function fromQueryBuilder(QueryBuilder $queryBuilder): self
	{
		return new self($queryBuilder->getQuerySql(), array_values($queryBuilder->getQueryParameters()));
	}
}

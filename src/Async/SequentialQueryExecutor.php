<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Nextras\Dbal\IConnection;


/**
 * Runs the queries one after another on the given connection. Works everywhere; used as the default and as fallback.
 */
final class SequentialQueryExecutor implements IQueryExecutor
{
	public function executeAll(IConnection $connection, array $queries): array
	{
		$results = [];
		foreach ($queries as $key => $query) {
			$result = $connection->queryArgs($query->sql, $query->args);
			$rows = [];
			while (($row = $result->fetch()) !== null) {
				$rows[] = $row->toArray();
			}
			$results[$key] = $rows;
		}
		return $results;
	}
}

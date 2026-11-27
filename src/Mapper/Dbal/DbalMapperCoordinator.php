<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidStateException;


class DbalMapperCoordinator
{
	/** Transaction nesting level opened by {@see beginTransaction()}, null if no transaction is active. */
	private int|null $transactionLevel = null;


	public function __construct(
		private readonly IConnection $connection,
	)
	{
	}


	public function beginTransaction(): void
	{
		if ($this->transactionLevel === null) {
			$this->connection->beginTransaction();
			$this->transactionLevel = $this->connection->getTransactionNestedIndex();
		}
	}


	public function flush(): void
	{
		if ($this->transactionLevel !== null) {
			$this->assertTransactionLevel('flush');
			$this->connection->commitTransaction();
			$this->transactionLevel = null;
		}
	}


	public function rollback(): void
	{
		if ($this->transactionLevel !== null) {
			$this->assertTransactionLevel('roll back');
			$this->connection->rollbackTransaction();
			$this->transactionLevel = null;
		}
	}


	/**
	 * Committing or rolling back always closes the innermost transaction level; it must be the one opened here,
	 * otherwise a transaction of someone else (e.g. IConnection::transactional()) would be closed instead.
	 */
	private function assertTransactionLevel(string $action): void
	{
		$currentLevel = $this->connection->getTransactionNestedIndex();
		if ($currentLevel === $this->transactionLevel) {
			return;
		}

		throw new InvalidStateException(
			"Cannot $action: the transaction was started by persisting at nesting level $this->transactionLevel, "
			. "but the connection is at level $currentLevel. "
			. ($currentLevel > $this->transactionLevel
				? 'A nested transaction was opened after persisting (e.g. by transactional()); '
					. 'flush before opening it, or persist inside it.'
				: 'The transaction was already committed or rolled back outside of Orm.'),
		);
	}
}

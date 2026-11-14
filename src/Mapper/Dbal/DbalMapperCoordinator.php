<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use Nextras\Dbal\Exception\TransactionRollbackException;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\ITransactionCoordinator;
use Throwable;


class DbalMapperCoordinator implements ITransactionCoordinator
{
	private bool $transactionActive = false;

	private bool $managedTransaction = false;

	private Throwable|null $rollbackFailure = null;


	public function __construct(
		private readonly IConnection $connection,
	)
	{
	}


	#[\NoDiscard]
	public function getConnection(): IConnection
	{
		return $this->connection;
	}


	public function beginTransaction(): void
	{
		$this->checkUsable();
		if (!$this->transactionActive) {
			$this->connection->beginTransaction();
			$this->transactionActive = true;
		}
	}


	public function flush(): void
	{
		$this->checkUsable();
		if ($this->transactionActive && !$this->managedTransaction) {
			$this->connection->commitTransaction();
			$this->transactionActive = false;
		}
	}


	public function rollback(): void
	{
		$this->checkUsable();
		if ($this->managedTransaction) {
			throw new InvalidStateException('Cannot roll back a mapper inside Model::transactional(). Throw an exception instead.');
		}
		if ($this->transactionActive) {
			try {
				$this->connection->rollbackTransaction();
			} catch (Throwable $e) {
				$this->rollbackFailure = $e;
				throw $e;
			} finally {
				$this->transactionActive = false;
			}
		}
	}


	public function transactional(callable $callback): mixed
	{
		$this->checkUsable();
		$this->connection->connect();
		if ($this->transactionActive || $this->managedTransaction || $this->connection->getTransactionNestedIndex() !== 0) {
			throw new InvalidStateException('Model::transactional() requires a connection without an active transaction.');
		}

		$this->managedTransaction = true;
		try {
			return $this->connection->transactional(function () use ($callback) {
				$this->transactionActive = true;
				return $callback();
			});
		} catch (TransactionRollbackException $e) {
			$this->rollbackFailure = $e;
			throw $e;
		} finally {
			$this->transactionActive = false;
			$this->managedTransaction = false;
		}
	}


	private function checkUsable(): void
	{
		if ($this->rollbackFailure !== null) {
			throw new InvalidStateException(
				'The transaction coordinator cannot be reused after rollback failed.',
				0,
				$this->rollbackFailure,
			);
		}
	}
}

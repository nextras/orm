<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidStateException;
use Throwable;


class DbalMapperCoordinator
{
	private bool $transactionActive = false;

	private Throwable|null $rollbackFailure = null;


	public function __construct(
		private readonly IConnection $connection,
	)
	{
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
		if ($this->transactionActive) {
			$this->connection->commitTransaction();
			$this->transactionActive = false;
		}
	}


	public function rollback(): void
	{
		$this->checkUsable();
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

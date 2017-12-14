<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nextras\Dbal\IConnection;


class DbalMapperCoordinator
{
	/** @var IConnection */
	private $connection;

	/** @var bool */
	private $transactionActive = false;


	public function __construct(IConnection $connection)
	{
		$this->connection = $connection;
	}


	public function beginTransaction()
	{
		if (!$this->transactionActive) {
			$this->connection->beginTransaction();
			$this->transactionActive = true;
		}
	}


	public function flush()
	{
		if ($this->transactionActive) {
			$this->connection->commitTransaction();
			$this->transactionActive = false;
		}
	}


	public function rollback()
	{
		if ($this->transactionActive) {
			$this->connection->rollbackTransaction();
			$this->transactionActive = false;
		}
	}
}

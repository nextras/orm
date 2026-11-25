<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


interface ITransactionCoordinator
{
	/**
	 * Runs an outermost transaction. Mapper flushes inside the callback must not commit it.
	 * Rejects entry if the resource already has an active transaction.
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public function transactional(callable $callback): mixed;
}

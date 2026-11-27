<?php declare(strict_types = 1);

namespace Nextras\Orm\Async\Swoole;


use Nextras\Dbal\Connection;
use Nextras\Orm\Exception\InvalidStateException;
use Swoole\Coroutine\Channel;
use Throwable;


/**
 * Coroutine-safe pool of DBAL connections sharing one config. Grows lazily up to $size connections;
 * {@see acquire()} suspends the calling coroutine while all of them are in use.
 * Connections connect lazily on their first query, so creating one never yields.
 */
final class SwooleConnectionPool
{
	/** @var Channel<Connection> */
	private readonly Channel $idle;
	private int $created = 0;


	public function __construct(
		/** @var array<string, mixed> */
		public readonly array $config,
		private readonly int $size,
	)
	{
		$this->idle = new Channel($size);
	}


	public function acquire(): Connection
	{
		if ($this->idle->isEmpty() === true && $this->created < $this->size) {
			$this->created++;
			try {
				return new Connection($this->config);
			} catch (Throwable $e) {
				$this->created--; // a failed attempt must not use up the pool capacity
				throw $e;
			}
		}

		$connection = $this->idle->pop();
		if (!$connection instanceof Connection) {
			throw new InvalidStateException('Unable to acquire a connection from the pool.');
		}
		return $connection;
	}


	public function release(Connection $connection): void
	{
		$this->idle->push($connection);
	}


	/**
	 * Disconnects the idle connections; used when the pool is replaced.
	 */
	public function close(): void
	{
		while (!$this->idle->isEmpty()) {
			$connection = $this->idle->pop(0.001);
			if ($connection instanceof Connection) {
				$connection->disconnect();
			}
		}
	}
}

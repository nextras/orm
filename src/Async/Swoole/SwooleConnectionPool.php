<?php declare(strict_types = 1);

namespace Nextras\Orm\Async\Swoole;


use Nextras\Dbal\Connection;
use Nextras\Orm\Exception\InvalidStateException;
use Swoole\Coroutine\Channel;


/**
 * Coroutine-safe pool of DBAL connections sharing one config. Grows lazily up to $size connections;
 * {@see acquire()} suspends the calling coroutine while all of them are in use.
 * Connections connect lazily on their first query, so creating one never yields.
 */
final class SwooleConnectionPool
{
	private readonly Channel $idle;
	private int $created = 0;


	public function __construct(
		/** @var array<string, mixed> */
		private readonly array $config,
		private readonly int $size,
	)
	{
		$this->idle = new Channel($size);
	}


	public function acquire(): Connection
	{
		if ($this->idle->isEmpty() === true && $this->created < $this->size) {
			$this->created++;
			return new Connection($this->config);
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
}

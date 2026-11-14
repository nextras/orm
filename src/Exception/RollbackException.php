<?php declare(strict_types = 1);

namespace Nextras\Orm\Exception;


use Throwable;


/**
 * Rollback or state cleanup failed. Discard the model and its entities after this exception.
 */
class RollbackException extends RuntimeException
{
	public function __construct(
		/** @var non-empty-list<Throwable> */
		private readonly array $cleanupErrors,
		Throwable|null $previous = null,
	)
	{
		parent::__construct('Failed to roll back or reset ORM state.', 0, $previous ?? $cleanupErrors[0]);
	}


	/**
	 * @return non-empty-list<Throwable>
	 */
	public function getCleanupErrors(): array
	{
		return $this->cleanupErrors;
	}
}

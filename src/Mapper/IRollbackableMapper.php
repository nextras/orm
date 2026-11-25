<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


/**
 * Optional capability for mappers whose unflushed writes can be rolled back.
 */
interface IRollbackableMapper
{
	public function rollback(): void;
}

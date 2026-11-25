<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


/**
 * Mappers sharing one storage transaction must return the same coordinator instance.
 */
interface ITransactionalMapper extends IRollbackableMapper
{
	#[\NoDiscard]
	public function getTransactionCoordinator(): ITransactionCoordinator;
}

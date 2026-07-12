<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\Definitions\ServiceDefinition;
use Nextras\Orm\Repository\IRepository;


class DiRepositoryEntry
{
	/**
	 * @param class-string<IRepository<*>> $className
	 */
	public function __construct(
		public readonly string $className,
		public readonly string|null $name,
		public readonly ServiceDefinition $service,
	)
	{
	}
}

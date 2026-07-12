<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Repository\IRepository;


class DIRepositoryFinder implements IRepositoryFinder
{
	#[\Override]
	public function __construct(
		protected readonly ContainerBuilder $builder,
		protected readonly OrmExtension $extension,
		protected readonly string $modelClass,
	)
	{
	}


	#[\Override]
	public function registerRepositories(): void
	{
	}


	#[\Override]
	public function resolveRepositories(): array
	{
		$repositories = [];
		$serviceDefinitions = $this->findRepositories();
		foreach ($serviceDefinitions as $serviceName => $serviceDefinition) {
			$className = $serviceDefinition->getType();
			if ($className === null) {
				throw new NotSupportedException(
					"Service definition $serviceName does have resolvable class type. " .
					"Such service definitions are not support by Nextras Orm."
				);
			} else if (!is_subclass_of($className, IRepository::class)) {
				throw new InvalidArgumentException(
					"Found '$className' repository is not an implementation of IRepository."
				);
			}
			$repositories[] = new DiRepositoryEntry(
				className: $className,
				name: $this->getRepositoryName($serviceName, $serviceDefinition),
				service: $serviceDefinition,
			);
		}
		return $repositories;
	}


	protected function getRepositoryName(string $serviceName, ServiceDefinition $serviceDefinition): string
	{
		return $serviceName;
	}


	/**
	 * @return array<string, ServiceDefinition>
	 */
	protected function findRepositories(): array
	{
		$services = [];
		foreach ($this->builder->findByType(IRepository::class) as $service) {
			$resolvedService = match (true) {
				$service instanceof ServiceDefinition => $service,
				$service instanceof FactoryDefinition => $service->getResultDefinition(),
				default => throw new NotSupportedException("Service " . $service::class . " type is not supported by Nextras Orm.")
			};
			$services[$resolvedService->getName() ?? $resolvedService->getType() ?? ""] = $resolvedService;
		}
		return $services;
	}
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use ReflectionClass;


class FilteredDIRepositoryFinder extends DIRepositoryFinder
{
	private string $modelClass;
	private ContainerBuilder $builder;


	public function __construct(string $modelClass, ContainerBuilder $containerBuilder, OrmExtension $extension)
	{
		if ($modelClass === Model::class) {
			throw new InvalidStateException('Your model has to inherit from ' . Model::class . '. Use compiler extension configuration - model key.');
		}

		$this->modelClass = $modelClass;
		$this->builder = $containerBuilder;

		parent::__construct($modelClass, $containerBuilder, $extension);
	}


	protected function findRepositories(): array
	{
		$reflection = new ReflectionClass($this->modelClass);
		$modelClassNamespace = $reflection->getNamespaceName();

		$types = $this->builder->findByType(IRepository::class);
		$filteredTypes = [];

		foreach ($types as $serviceName => $serviceDefinition) {
			$serviceName = (string) $serviceName;
			if ($serviceDefinition instanceof FactoryDefinition) {
				$repositoryType = $serviceDefinition->getResultDefinition()->getType();

			} elseif ($serviceDefinition instanceof ServiceDefinition || $serviceDefinition instanceof \Nette\DI\ServiceDefinition) { // @phpstan-ignore-line
				$repositoryType = $serviceDefinition->getType();

			} else {
				$type = $serviceDefinition->getType();
				throw new InvalidStateException(
					"It seems DI defined repository of type '$type' is not defined as one of supported DI services.
					Orm can only work with ServiceDefinition or FactoryDefinition services.",
				);
			}

			if (str_starts_with($repositoryType, $modelClassNamespace)) {
				$filteredTypes[$serviceName] = $serviceDefinition;
			}
		}

		return $filteredTypes;
	}

}

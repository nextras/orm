<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use ReflectionClass;


class FilteredDIRepositoryFinder extends DIRepositoryFinder
{

	/**
	 * @param class-string<IModel> $modelClass
	 */
	public function __construct(
		private readonly string $modelClass,
		private readonly ContainerBuilder $builder,
		OrmExtension $extension
	)
	{
		if ($this->modelClass === Model::class) {
			throw new InvalidStateException('Your model has to inherit from ' . Model::class . '. Use compiler extension configuration - model key.');
		}

		parent::__construct($this->modelClass, $builder, $extension);
	}


	protected function findRepositories(): array
	{
		$reflection = new ReflectionClass($this->modelClass);
		$modelClassNamespace = $reflection->getNamespaceName();

		$types = $this->builder->findByType(IRepository::class);
		$filteredTypes = [];

		foreach ($types as $serviceName => $serviceDefinition) {
			$serviceName = (string) $serviceName;
			
			/** @var class-string<IRepository<IEntity>> $type */
			$type = $serviceDefinition->getType();

			$isSupportedDefinition =
				$serviceDefinition instanceof FactoryDefinition ||
				$serviceDefinition instanceof ServiceDefinition ||
				$serviceDefinition instanceof \Nette\DI\ServiceDefinition;

			if ($isSupportedDefinition === false) {
				throw new InvalidStateException(
					"It seems DI defined repository of type '$type' is not defined as one of supported DI services.
					Orm can only work with ServiceDefinition or FactoryDefinition services.",
				);
			}

			if ($type === null) {
				continue;
			}

			if (str_starts_with($type, $modelClassNamespace)) {
				$filteredTypes[$serviceName] = $serviceDefinition;
			}
		}

		return $filteredTypes;
	}

}

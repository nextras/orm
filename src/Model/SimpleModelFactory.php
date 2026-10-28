<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\Caching\Cache;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Extension;
use Nextras\Orm\Repository\IRepository;


class SimpleModelFactory
{
	public function __construct(
		private readonly Cache $cache,
		/**
		 * @var array<string, IRepository<*>> Map of a repository name and its instance. The name is used
		 * for accessing repository by name in contrast to accessing by class-string.
		 */
		private readonly array $repositories,
		private readonly IMetadataParserFactory|null $metadataParserFactory = null,
		/** @var list<Extension> */
		private readonly array $extensions = [],
	)
	{
	}


	public function create(): Model
	{
		$entityClassesMap = $this->getEntityClassesMap();
		$parser = $this->metadataParserFactory ?? new MetadataParserFactory($this->extensions);
		$loader = new SimpleRepositoryLoader($this->repositories, $entityClassesMap);
		$metadata = new MetadataStorage($entityClassesMap, $this->cache, $parser, $loader);
		$model = new Model($loader, $metadata);

		foreach ($this->repositories as $repository) {
			$repository->setModel($model);
		}

		foreach ($this->extensions as $extension) {
			$extension->configureModel($model);
			foreach ($this->repositories as $repository) {
				$extension->configureRepository($repository);
				$extension->configureMapper($repository->getMapper());
			}
		}

		return $model;
	}


	/**
	 * @return array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 */
	private function getEntityClassesMap(): array
	{
		$map = [];
		foreach ($this->repositories as $repository) {
			foreach ($repository::getEntityClassNames() as $entityClassName) {
				$map[$entityClassName] = $repository::class;
			}
		}
		/** @var array<class-string<IEntity>, class-string<IRepository<IEntity>>> $map */
		return $map;
	}
}

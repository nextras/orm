<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\Caching\Cache;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Repository\IRepository;
use function array_values;


class SimpleModelFactory
{
	/**
	 * @template E of IEntity
	 * @param array<string, IRepository<E>> $repositories Map of a repository name and its instance. The name is used
	 * for accessing repository by name in contrast to accessing by class-string.
	 */
	public function __construct(
		private readonly Cache $cache,
		private readonly array $repositories,
		private readonly IMetadataParserFactory|null $metadataParserFactory = null,
	)
	{
	}


	public function create(): Model
	{
		$config = Model::getConfiguration($this->repositories);
		$parser = $this->metadataParserFactory ?? new MetadataParserFactory();
		$loader = new SimpleRepositoryLoader(array_values($this->repositories));
		$metadata = new MetadataStorage($config[2], $this->cache, $parser, $loader);
		$model = new Model($config, $loader, $metadata);

		foreach ($this->repositories as $repository) {
			$repository->setModel($model);
		}

		return $model;
	}
}

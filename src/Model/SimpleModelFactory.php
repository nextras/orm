<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Caching\Cache;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Repository\IRepository;


class SimpleModelFactory
{
	/** @var Cache */
	private $cache;

	/** @var IRepository[] */
	private $repositories;

	/** @var IMetadataParserFactory|null */
	private $metadataParserFactory;


	public function __construct(Cache $cache, array $repositories, IMetadataParserFactory $metadataParserFactory = null)
	{
		$this->cache = $cache;
		$this->repositories = $repositories;
		$this->metadataParserFactory = $metadataParserFactory;
	}


	/**
	 * @return Model
	 */
	public function create()
	{
		$config   = Model::getConfiguration($this->repositories);
		$parser   = $this->metadataParserFactory ?: new MetadataParserFactory();
		$loader   = new SimpleRepositoryLoader($this->repositories);
		$metadata = new MetadataStorage($config[2], $this->cache, $parser, $loader);
		$model    = new Model($config, $loader, $metadata);

		foreach ($this->repositories as $repository) {
			$repository->setModel($model);
		}

		return $model;
	}
}

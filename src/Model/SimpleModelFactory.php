<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Caching\IStorage;
use Nette\Object;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Repository\IRepository;


class SimpleModelFactory extends Object
{
	/** @var IStorage */
	private $storage;

	/** @var IRepository[] */
	private $repositories;

	/** @var IMetadataParserFactory */
	private $metadataParserFactory;


	public function __construct(IStorage $storage, array $repositories, IMetadataParserFactory $metadataParserFactory = NULL)
	{
		$this->storage = $storage;
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
		$metadata = new MetadataStorage($config[2], $this->storage, $parser, $loader);
		$model    = new Model($config, $loader, $metadata);

		foreach ($this->repositories as $repository) {
			$repository->setModel($model);
		}

		return $model;
	}
}

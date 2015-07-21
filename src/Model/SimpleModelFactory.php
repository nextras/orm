<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Caching\IStorage;
use Nette\Object;
use Nextras\Orm\Repository\IRepository;


class SimpleModelFactory extends Object
{
	/** @var IStorage */
	private $storage;

	/** @var IRepository[] */
	private $repositories;


	public function __construct(IStorage $storage, array $repositories)
	{
		$this->storage = $storage;
		$this->repositories = $repositories;
	}


	/**
	 * @return Model
	 */
	public function create()
	{
		$config   = Model::getConfiguration($this->repositories);
		$loader   = new SimpleRepositoryLoader($this->repositories);
		$metadata = new MetadataStorage($this->storage, $config[2], $loader);
		$model    = new Model($config, $loader, $metadata);

		foreach ($this->repositories as $repository) {
			$repository->setModel($model);
		}

		return $model;
	}

}

<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;
use Nextras\Orm\TestHelper\EntityCreator;
use Nextras\Orm\TestHelper\TestMapper;


class OrmTestsExtension extends OrmExtension
{
	/** @var bool */
	private $testingMappers = true;


	public function loadConfiguration()
	{
		$config = $this->getConfig(['testingMappers' => true]);
		$this->testingMappers = $config['testingMappers'];
		parent::loadConfiguration();
		$this->setupEntityCreator();
	}


	protected function setupEntityCreator()
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('testing.entityCreator'))
			->setClass(EntityCreator::class)
			->setArguments(['@' . $this->prefix('model')]);
	}


	protected function createMapperService($repositoryName, $repositoryClass, ContainerBuilder $builder)
	{
		if (!$this->testingMappers) {
			return parent::createMapperService($repositoryName, $repositoryClass, $builder);
		}

		$testMapperName = $this->prefix('mappers.testing.' . $repositoryName);
		if (!$builder->hasDefinition($testMapperName)) {
			$mapperClass = TestMapper::class;
			$builder->addDefinition($testMapperName)->setClass($mapperClass);
		}

		return $testMapperName;
	}
}

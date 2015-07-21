<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;


class OrmTestsExtension extends OrmExtension
{
	/** @var bool */
	private $testingMappers = TRUE;


	public function loadConfiguration()
	{
		$config = $this->getConfig(['testingMappers' => TRUE]);
		$this->testingMappers = $config['testingMappers'];
		parent::loadConfiguration();
		$this->setupEntityCreator();
	}


	protected function setupEntityCreator()
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('testing.entityCreator'))
			->setClass('Nextras\Orm\TestHelper\EntityCreator')
			->setArguments(['@' . $this->prefix('model')]);
	}


	protected function createMapperService($repositoryName, $repositoryClass, ContainerBuilder $builder)
	{
		if (!$this->testingMappers) {
			return parent::createMapperService($repositoryName, $repositoryClass, $builder);
		}

		$testMapperName = $this->prefix('mappers.testing.' . $repositoryName);
		if (!$builder->hasDefinition($testMapperName)) {
			$mapperClass = 'Nextras\Orm\TestHelper\TestMapper';
			$builder->addDefinition($testMapperName)->setClass($mapperClass);
		}

		return $testMapperName;
	}

}

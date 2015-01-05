<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Bridge\NetteDI;

use Nette\DI\ContainerBuilder;


class OrmTestsExtension extends OrmExtension
{
	/** @var bool */
	private $testingMappers = TRUE;


	public function loadConfiguration()
	{
		parent::loadConfiguration();
		$config = $this->getConfig(['testingMappers' => TRUE]);
		$this->testingMappers = $config['testingMappers'];
	}


	public function beforeCompile()
	{
		parent::beforeCompile();
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

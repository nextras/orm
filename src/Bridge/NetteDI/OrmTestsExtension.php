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
		$mapperName = parent::createMapperService($repositoryName, $repositoryClass, $builder);

		$testMapperName = $this->prefix('mappers.testing.' . $repositoryName);
		if (!$builder->hasDefinition($testMapperName)) {
			$mapperClass = 'Nextras\Orm\TestHelper\TestMapper';
			$builder->addDefinition($testMapperName)
				->setClass($mapperClass)
				->setArguments(['@' . $mapperName]);
		}

		return $testMapperName;
	}

}

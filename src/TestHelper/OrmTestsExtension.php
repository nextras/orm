<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\TestHelper;

use Nette\DI\ContainerBuilder;
use Nextras\Orm\DI\OrmExtension;


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


	protected function createMapperService($repositoryData, ContainerBuilder $builder)
	{
		$mapperName = parent::createMapperService($repositoryData, $builder);

		$testMapperName = $this->prefix('mappers.testing.' . $repositoryData['name']);
		if (!$builder->hasDefinition($testMapperName)) {
			$mapperClass = 'Nextras\Orm\TestHelper\TestMapper';
			$builder->addDefinition($testMapperName)
				->setClass($mapperClass)
				->setArguments(['@' . $mapperName]);
		}

		return $testMapperName;
	}

}

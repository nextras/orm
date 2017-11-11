<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nextras\Orm\TestHelper\TestMapper;


class TestMapperPhpDocRepositoryFinder extends PhpDocRepositoryFinder
{
	protected function setupMapperService(string $repositoryName, string $repositoryClass)
	{
		$mapperName = $this->extension->prefix('mappers.' . $repositoryName);
		if ($this->builder->hasDefinition($mapperName)) {
			return;
		}

		$this->builder->addDefinition($mapperName)
			->setClass(TestMapper::class);
	}
}

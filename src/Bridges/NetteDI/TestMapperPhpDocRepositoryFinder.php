<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nextras\Orm\TestHelper\TestMapper;


class TestMapperPhpDocRepositoryFinder extends PhpDocRepositoryFinder
{
	protected function setupMapperService(string $repositoryName, string $repositoryClass): void
	{
		$mapperName = $this->extension->prefix('mappers.' . $repositoryName);
		if ($this->builder->hasDefinition($mapperName)) {
			return;
		}

		$this->builder->addDefinition($mapperName)
			->setType(TestMapper::class);
	}
}

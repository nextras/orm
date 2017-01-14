<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;


interface IRepositoryFinder
{
	/**
	 * @return array
	 */
	public function initRepositories($modelClass, ContainerBuilder $containerBuilder, callable $prefixCb);
}

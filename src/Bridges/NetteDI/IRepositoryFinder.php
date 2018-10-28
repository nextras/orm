<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;


interface IRepositoryFinder
{
	public function __construct(string $modelClass, ContainerBuilder $containerBuilder, OrmExtension $extension);


	/**
	 * Load configuration DIC phase.
	 * Returns array of repositores or null if they are loaded in the other phase.
	 */
	public function loadConfiguration(): ?array;


	/**
	 * Before compile DIC phase.
	 * Returns array of repositores or null if they are loaded in the other phase.
	 */
	public function beforeCompile(): ?array;
}

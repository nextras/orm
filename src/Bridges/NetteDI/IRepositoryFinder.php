<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;


interface IRepositoryFinder
{
	/**
	 * @phpstan-param class-string<\Nextras\Orm\Model\IModel> $modelClass
	 */
	public function __construct(string $modelClass, ContainerBuilder $containerBuilder, OrmExtension $extension);


	/**
	 * Load configuration DIC phase.
	 * Returns array of repositories or null if they are loaded in the other phase.
	 * @return array<string, class-string<\Nextras\Orm\Repository\IRepository>>
	 */
	public function loadConfiguration(): ?array;


	/**
	 * Before compile DIC phase.
	 * Returns array of repositories or null if they are loaded in the other phase.
	 * @return array<string, class-string<\Nextras\Orm\Repository\IRepository>>
	 */
	public function beforeCompile(): ?array;
}

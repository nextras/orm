<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Statement;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;


interface IRepositoryFinder
{
	/**
	 * @param class-string<IModel> $modelClass
	 * @param list<Statement> $extensions
	 */
	public function __construct(string $modelClass, array $extensions, ContainerBuilder $containerBuilder, OrmExtension $extension);


	/**
	 * Load configuration DIC phase.
	 * Returns array of repositories or null if they are loaded in the other phase.
	 * @return array<string, class-string<IRepository<IEntity>>>
	 */
	public function loadConfiguration(): ?array;


	/**
	 * Before compile DIC phase.
	 * Returns array of repositories or null if they are loaded in the other phase.
	 * @return array<string, class-string<IRepository<IEntity>>>
	 */
	public function beforeCompile(): ?array;
}

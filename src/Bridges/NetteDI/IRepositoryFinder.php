<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nextras\Orm\Model\IModel;


interface IRepositoryFinder
{
	/**
	 * @param class-string<IModel> $modelClass
	 */
	public function __construct(
		ContainerBuilder $builder,
		OrmExtension $extension,
		string $modelClass,
	);

	/**
	 * Registers repositories.
	 *
	 * The repository finder may not do anything if it reuses already registered repositories.
	 */
	public function registerRepositories(): void;

	/**
	 * Resolves a list of repositories for the current $modelClass.
	 * The {@see OrmExtension} will reuse discovered service definition for final setup with the Model.
	 *
	 * @return list<DiRepositoryEntry>
	 */
	public function resolveRepositories(): array;
}

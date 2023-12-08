<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;


interface IMetadataParserFactory
{
	/**
	 * Creates metadata parser.
	 * @param array<string, string> $entityClassesMap
	 * @param array<class-string<IEntity>, class-string<IRepository<IEntity>>> $entityClassesMap
	 */
	public function create(array $entityClassesMap): IMetadataParser;
}

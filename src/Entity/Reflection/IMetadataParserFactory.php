<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


interface IMetadataParserFactory
{
	/**
	 * Creates metadata parser.
	 * @param array<string, string> $entityClassesMap
	 * @phpstan-param array<class-string<\Nextras\Orm\Entity\IEntity>, class-string<\Nextras\Orm\Repository\IRepository>> $entityClassesMap
	 */
	public function create(array $entityClassesMap): IMetadataParser;
}

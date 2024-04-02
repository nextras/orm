<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


interface IMetadataParser
{
	/**
	 * Parses metadata for entity.
	 * @param class-string $entityClass
	 * @param list<string>|null $fileDependencies
	 */
	public function parseMetadata(string $entityClass, array|null &$fileDependencies): EntityMetadata;
}

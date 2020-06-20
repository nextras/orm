<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


interface IMetadataParser
{
	/**
	 * Parses metadata for entity.
	 * @phpstan-param class-string $entityClass
	 * @phpstan-param list<string>|null $fileDependencies
	 */
	public function parseMetadata(string $entityClass, ?array &$fileDependencies): EntityMetadata;
}

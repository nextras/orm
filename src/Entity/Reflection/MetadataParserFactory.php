<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


class MetadataParserFactory implements IMetadataParserFactory
{
	public function create(array $entityClassesMap): IMetadataParser
	{
		return new MetadataParser($entityClassesMap);
	}
}

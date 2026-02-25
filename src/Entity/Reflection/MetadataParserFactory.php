<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nextras\Orm\Extension;


class MetadataParserFactory implements IMetadataParserFactory
{
	/** @param list<Extension> $extensions */
	public function __construct(
		private readonly array $extensions = [],
	)
	{
	}


	public function create(array $entityClassesMap): IMetadataParser
	{
		return new MetadataParser($entityClassesMap, $this->extensions);
	}
}

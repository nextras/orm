<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;


class MetadataParserFactory implements IMetadataParserFactory
{
	public function create(array $entityClassesMap): IMetadataParser
	{
		return new MetadataParser($entityClassesMap);
	}
}

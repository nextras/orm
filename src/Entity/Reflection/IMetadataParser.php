<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;


interface IMetadataParser
{
	/**
	 * Parses metadata for entity.
	 * @param  string $entityClass
	 * @param  array|null $fileDependencies
	 * @return EntityMetadata
	 */
	public function parseMetadata($entityClass, & $fileDependencies);
}

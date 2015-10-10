<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;


interface IMetadataParserFactory
{
	/**
	 * Creates metadata parser.
	 * @param  array $entityClassesMap
	 * @return \Nextras\Orm\Entity\Reflection\IMetadataParser
	 */
	public function create(array $entityClassesMap);
}

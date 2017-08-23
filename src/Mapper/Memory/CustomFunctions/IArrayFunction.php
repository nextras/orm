<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory\CustomFunctions;

use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;


interface IArrayFunction
{
	public function processArrayFilter(ArrayCollectionHelper $helper, array $collection, array $args): array;
}

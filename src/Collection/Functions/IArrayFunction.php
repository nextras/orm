<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Functions;

use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Entity\IEntity;


/**
 * Collection function implementation for ArrayCollection.
 * Processes expression and reuse its result for futher evaluation.
 */
interface IArrayFunction
{
	/**
	 * Returns a value depending on values of entity; the expression passed by args is evaluated during this method
	 * execution.
	 * Usually returns simply a boolean for filtering evaluation.
	 * @param array<mixed> $args
	 * @return mixed
	 */
	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args);
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


/**
 * TODO
 */
interface IMultiPropertyPropertyContainer extends IProperty
{
	public function getRawValueOf(array $path, bool $checkPropertyExistence = true);
}

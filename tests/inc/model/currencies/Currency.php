<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;


/**
 * @property string $id {primary-proxy}
 * @property string $code {primary}
 * @property string $name
 */
class Currency extends Entity
{
	public function __construct(string $code, string $name)
	{
		parent::__construct();
		$this->code = $code;
		$this->name = $name;
	}
}

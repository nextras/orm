<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm;


class NoResultException extends RuntimeException
{
	public function __construct()
	{
		parent::__construct('No result was found, at least one entity was expected.');
	}
}

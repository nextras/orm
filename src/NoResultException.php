<?php declare(strict_types = 1);

namespace Nextras\Orm;


class NoResultException extends RuntimeException
{
	public function __construct()
	{
		parent::__construct('No result was found, at least one entity was expected.');
	}
}

<?php

namespace NextrasTests\Orm;

use Nette\Database\Drivers\PgSqlDriver;
use Nextras\Orm\Mapper\Mapper;


final class AuthorsMapper extends Mapper
{

	public function getTableName()
	{
		if ($this->databaseContext->getConnection()->getSupplementalDriver() instanceof PgSqlDriver) {
			return 'public.authors';
		} else {
			return 'authors';
		}
	}

}

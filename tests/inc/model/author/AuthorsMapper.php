<?php

namespace NextrasTests\Orm;

use Nextras\Dbal\Drivers\Postgre\PostgreDriver;
use Nextras\Orm\Mapper\Mapper;


final class AuthorsMapper extends Mapper
{

	public function getTableName()
	{
		if ($this->connection->getDriver() instanceof PostgreDriver) {
			return 'public.authors';
		} else {
			return 'authors';
		}
	}

}

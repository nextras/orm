<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Dbal\Drivers\Postgre\PostgreDriver;
use Nextras\Orm\Mapper\Mapper;


final class AuthorsMapper extends Mapper
{
	public function getTableName(): string
	{
		if ($this->connection->getDriver() instanceof PostgreDriver) {
			return 'public.authors';
		} else {
			return 'authors';
		}
	}
}

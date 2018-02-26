<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Mapper\Mapper;


final class AuthorsMapper extends Mapper
{
	public function getTableName(): string
	{
		if ($this->connection->getPlatform()->getName() == 'pgsql') {
			return 'public.authors';
		} else {
			return 'authors';
		}
	}
}

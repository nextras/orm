<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Mapper\Dbal\DbalMapper;


/**
 * @extends DbalMapper<Author>
 */
final class AuthorsMapper extends DbalMapper
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

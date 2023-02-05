<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Orm\Mapper\Dbal\DbalMapper;


/**
 * @extends DbalMapper<Author>
 */
final class AuthorsMapper extends DbalMapper
{
	/**
	 * @return literal-string|array{literal-string, literal-string}
	 */
	public function getTableName(): string|array
	{
		if ($this->connection->getPlatform()->getName() === PostgreSqlPlatform::NAME) {
			return ['public', 'authors'];
		} else {
			return 'authors';
		}
	}
}

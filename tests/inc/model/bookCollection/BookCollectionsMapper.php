<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\IPersistAutoupdateMapper;


class BookCollectionsMapper extends DbalMapper implements IPersistAutoupdateMapper
{
	public function getAutoupdateReselectExpression(): array
	{
		return ['%column[]', ['id', 'updated_at']];
	}
}

<?php declare(strict_types = 1);

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

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Mapper;


final class TagsMapper extends Mapper
{
	protected function createConventions(): IConventions
	{
		$reflection = parent::createConventions();
		assert($reflection instanceof Conventions);
		$reflection->addMapping('isGlobal', 'is_global', function ($val): bool {
			return $val === 'y';
		}, function ($val) {
			return $val ? 'y' : 'n';
		});
		return $reflection;
	}
}

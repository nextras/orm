<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Mapper;


final class TagsMapper extends Mapper
{
	protected function createConventions(): IConventions
	{
		$conventions = parent::createConventions();
		$conventions->addMapping(
			'isGlobal',
			'is_global',
			function ($val): bool {
				return $val === 'y';
			},
			function ($val) {
				return $val === true ? 'y' : 'n';
			}
		);
		return $conventions;
	}
}

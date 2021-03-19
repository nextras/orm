<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;


/**
 * @extends DbalMapper<Tag>
 */
final class TagsMapper extends DbalMapper
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

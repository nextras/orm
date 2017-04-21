<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Mapper\Mapper;


final class TagsMapper extends Mapper
{
	protected function createStorageReflection()
	{
		$reflection = parent::createStorageReflection();
		$reflection->addMapping('isGlobal', 'is_global', function ($val) {
			return $val === 'y';
		}, function ($val) {
			return $val ? 'y' : 'n';
		});
		return $reflection;
	}
}

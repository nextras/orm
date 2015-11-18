<?php

namespace NextrasTests\Orm;


final class TagsMapper extends PoCChangeMapper
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


	protected function getReturningClause()
	{
		return ['RETURNING ascii(%column) as %column', 'name', 'computed_property'];
	}

}

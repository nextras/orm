<?php

namespace NextrasTests\Orm;


final class TagsMapper extends SelfUpdatingPropertyMapper
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


	protected function getSelfUpdatingProperties()
	{
		return ['computedProperty'];
	}

}

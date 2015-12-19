<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Mapper\Mapper;


final class PublishersMapper extends Mapper
{
	protected function createStorageReflection()
	{
		$reflection = parent::createStorageReflection();
		$reflection->addMapping('publisherId', 'id');
		return $reflection;
	}
}

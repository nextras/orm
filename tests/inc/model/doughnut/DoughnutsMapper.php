<?php

namespace NextrasTests\Orm;


final class DoughnutsMapper extends SelfUpdatingPropertyMapper
{

	protected function getSelfUpdatingProperties()
	{
		return ['computedProperty'];
	}

}

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


final class TagsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Tag::class];
	}
}

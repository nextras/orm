<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Tag>
 */
final class TagsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Tag::class];
	}
}

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Folder>
 */
final class FoldersRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Folder::class];
	}
}

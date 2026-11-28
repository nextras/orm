<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Document>
 */
final class DocumentsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Document::class];
	}
}

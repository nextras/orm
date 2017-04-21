<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Author|NULL getById($id)
 */
final class AuthorsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Author::class];
	}


	public function findByTags($name)
	{
		return $this->findBy(['this->books->tags->name' => $name]);
	}
}

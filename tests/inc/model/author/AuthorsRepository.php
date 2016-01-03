<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Author|NULL getById($id)
 */
final class AuthorsRepository extends Repository
{
	static function getEntityClassNames()
	{
		return [Author::class];
	}


	public function findByTags($name)
	{
		return $this->findBy(['this->books->tags->name' => $name]);
	}
}

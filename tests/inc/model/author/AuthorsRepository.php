<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Repository\Repository;


/**
 * @method Author getById($id)
 */
final class AuthorsRepository extends Repository
{

	public function findByTags($name)
	{
		return $this->findBy(['this->books->tags->name' => $name]);
	}

}

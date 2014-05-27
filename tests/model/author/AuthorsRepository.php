<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Repository\Repository;


final class AuthorsRepository extends Repository
{

	public function findByTags($name)
	{
		return $this->findBy(['this->books->tags.name' => $name]);
	}

}

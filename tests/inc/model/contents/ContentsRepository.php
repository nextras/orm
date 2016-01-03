<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Thread|Comment|NULL getById($id)
 */
class ContentsRepository extends Repository
{
	public static function getEntityClassNames()
	{
		return [Comment::class,	Thread::class];
	}


	public function getEntityClassName(array $data)
	{
		return $data['type'] === 'comment' ? Comment::class : Thread::class;
	}
}

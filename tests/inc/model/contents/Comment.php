<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property-read string        $type       {default comment}
 * @property      Thread|NULL   $thread     {m:1 Thread::$comments}
 */
class Comment extends Entity
{
}

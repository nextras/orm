<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany as MHM;


/**
 * @property int        $id            {primary}
 * @property MHM|User[] $myFriends     {m:m User::$friendsWithMe, isMain=true}
 * @property MHM|User[] $friendsWithMe {m:m User::$myFriends}
 */
final class User extends Entity
{
}

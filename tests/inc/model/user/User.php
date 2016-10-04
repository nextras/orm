<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany as MHM;

/**
 * @property int                 $id             {primary}
 * @property ManyHasMany|User[]  $myFriends      {m:m User::$friendsWithMe, isMain=true}
 * @property ManyHasMany|User[]  $friendsWithMe  {m:m User::$myFriends}
 */
final class User extends Entity
{
}

<?php

namespace NextrasTests\Orm;

use DateTime;
use Nextras\Orm\Entity\Entity;


/**
 * @property Tag       $tag        {m:1 Tag} {primary}
 * @property Author    $author     {m:1 Author} {primary}
 * @property DateTime  $createdAt  {default now}
 */
final class TagFollower extends Entity
{
}

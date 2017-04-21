<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;


/**
 * @property array             $id        {primary-proxy}
 * @property Tag               $tag       {m:1 Tag::$tagFollowers} {primary}
 * @property Author            $author    {m:1 Author::$tagFollowers} {primary}
 * @property DateTimeImmutable $createdAt {default now}
 */
final class TagFollower extends Entity
{
}

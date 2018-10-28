<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property array             $id        {primary-proxy}
 * @property Author            $author    {m:1 Author::$tagFollowers} {primary}
 * @property Tag               $tag       {m:1 Tag::$tagFollowers} {primary}
 */
final class TagFollower extends Entity
{
    use CreatedColumnTrait;
}

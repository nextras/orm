<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property int|null         $id          {primary-proxy}
 * @property int|null         $publisherId {primary}
 * @property string           $name
 * @property OneHasMany<Book> $books       {1:m Book::$publisher}
 * @property ManyHasMany<Tag> $tags        {m:m Tag::$publishers, isMain=true}
 */
final class Publisher extends Entity
{
}

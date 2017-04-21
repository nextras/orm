<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property int        $id          {primary-proxy}
 * @property int        $publisherId {primary}
 * @property string     $name
 * @property OHM|Book[] $books       {1:m Book::$publisher}
 */
final class Publisher extends Entity
{
}

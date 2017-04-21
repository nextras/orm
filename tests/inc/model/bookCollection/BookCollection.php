<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;


/**
 * @property int                    $id {primary}
 * @property string                 $name
 * @property DateTimeImmutable|null $updatedAt
 */
class BookCollection extends Entity
{
}

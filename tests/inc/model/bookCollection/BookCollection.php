<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

use DateTime;
use Nextras\Orm\Entity\Entity;


/**
 * @property int            $id {primary}
 * @property string         $name
 * @property DateTime|NULL  $updatedAt
 */
class BookCollection extends Entity
{
}

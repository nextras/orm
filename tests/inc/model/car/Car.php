<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;


/**
 * @property int      $id           {primary}
 * @property string   $name
 * @property FuelType $fuelType {default FuelType::DIESEL}
 */
final class Car extends Entity
{
}

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;


/**
 * @property int|null $id {primary}
 * @property AdminPersonalData $personalData {1:1 AdminPersonalData::$admin, cascade=[persist, remove]}
 */
final class Admin extends Entity
{

}

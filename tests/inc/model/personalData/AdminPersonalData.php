<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


/**
 * @property Admin $admin {1:1 Admin::$personalData, isMain=true}
 * @property string $firstName
 * @property string $lastName
 */
final class AdminPersonalData extends PersonalData
{

}

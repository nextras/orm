<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Exception;
use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<PersonalData>
 */
final class PersonalDataRepository extends Repository
{

	public static function getEntityClassNames(): array
	{
		return [PersonalData::class, AdminPersonalData::class];
	}

	public function getEntityClassName(array $data): string
	{
		return isset($data['admin'])
			? AdminPersonalData::class
			: throw new Exception();
	}

}

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Car>
 */
final class CarsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Car::class];
	}


	public function getByName(string $name): ?Car
	{
		return $this->getBy(['name' => $name]);
	}
}

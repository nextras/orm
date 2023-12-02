<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\FuelType;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionEnumOnEntitiesTest extends DataTestCase
{
	public function testEntityEnumType(): void
	{
		$collection = $this->orm->cars->findBy([
			'fuelType' => [
				FuelType::DIESEL,
				FuelType::ELECTRIC,
				FuelType::PETROL,
				FuelType::HYBRID,
			],
		]);
		$collection = $collection->orderBy('id');
		Assert::same(3, $collection->countStored());

		foreach ($collection as $car) {
			Assert::type(FuelType::class, $car->fuelType);
		}
	}

}


$test = new CollectionEnumOnEntitiesTest();
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use Nextras\Orm\Exception\InvalidArgumentException;
use NextrasTests\Orm\Car;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\FuelType;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityEnumPropTest extends DataTestCase
{

	public function testEnumOnEntity(): void
	{
		/** @var Car $car */
		$car = $this->orm->cars->findAll()->fetch();

		Assert::notNull($car);

		Assert::same(FuelType::HYBRID, $car->fuelType);
	}


	public function testAddEntityWithEnum(): void
	{
		$carName = 'Tesla Model S';

		$car = new Car();
		$car->fuelType = FuelType::ELECTRIC;
		$car->name = $carName;
		$this->orm->cars->persistAndFlush($car);

		Assert::same(FuelType::ELECTRIC, $car->fuelType);

		$entity = $this->orm->cars->getBy(['name' => $carName]);
		Assert::notNull($entity);

		Assert::type(FuelType::class, $entity->fuelType);

		Assert::same(FuelType::ELECTRIC, $entity->fuelType);
	}


	public function testAddEntityWithDefaultEnum(): void
	{
		$carName = 'Volkswagen Golf';

		$car = new Car();
		$car->name = $carName;
		$this->orm->cars->persistAndFlush($car);

		Assert::same(FuelType::DIESEL, $car->fuelType);

		$entity = $this->orm->cars->getBy(['name' => $carName]);
		Assert::notNull($entity);

		Assert::type(FuelType::class, $entity->fuelType);

		Assert::same(FuelType::DIESEL, $entity->fuelType);
	}


	public function testAddEntityWithUnknownEnum(): void
	{
		$carName = 'Toyota Mirai';

		$car = new Car();
		$car->name = $carName;
		$car->fuelType = 'hydrogen';

		Assert::exception(function () use ($car) {
			$this->orm->cars->persistAndFlush($car);
		}, InvalidArgumentException::class);

	}

}


$test = new EntityEnumPropTest();
$test->run();

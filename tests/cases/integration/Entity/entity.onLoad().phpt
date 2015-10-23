<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;

use Mockery;
use Nextras\Orm\Model\IModel;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\JsonProxy;
use NextrasTests\Orm\LocationStruct;
use NextrasTests\Orm\Publisher;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class OnLoadEntityTest extends DataTestCase
{

	public function testOnLoad()
	{
		$publisher = new Publisher();
		$publisher->name = 'Albatros';
		$publisher->location = new LocationStruct('street', 'city');

		Assert::type(JsonProxy::class, $publisher->getProperty('location'));
		Assert::type(LocationStruct::class, $publisher->location);
		$this->orm->publishers->persistAndFlush($publisher);
		$id = $publisher->id;

		$this->orm->clearIdentityMapAndCaches(IModel::I_KNOW_WHAT_I_AM_DOING);

		$publisher = $this->orm->publishers->getById($id);
		Assert::type(JsonProxy::class, $publisher->getProperty('location'));
		Assert::type(LocationStruct::class, $publisher->location);
		Assert::same('street', $publisher->location->getStreet());
		Assert::same('city', $publisher->location->getCity());
	}

}


$test = new OnLoadEntityTest($dic);
$test->run();

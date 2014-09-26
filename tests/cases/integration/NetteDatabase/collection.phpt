<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionTest extends DatabaseTestCase
{

	public function testCountOnLimited()
	{
		$collection = $this->orm->books->findAll();
		$collection = $collection->limitBy(1, 1);
		Assert::same(1, $collection->count());

		$collection = $collection->limitBy(1, 10);
		Assert::same(0, $collection->count());
	}

}


$test = new CollectionTest($dic);
$test->run();

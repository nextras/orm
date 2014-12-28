<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use NextrasTests\Orm\DatabaseTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MapperSelectionTest extends DatabaseTestCase
{

	public function testCloningPersisted()
	{
		$books = $this->orm->books->getBooksWithEvenId()->fetchPairs(NULL, 'id');
		Assert::same([2, 4], $books);
	}

}


$test = new MapperSelectionTest($dic);
$test->run();

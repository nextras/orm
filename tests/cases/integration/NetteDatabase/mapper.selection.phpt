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

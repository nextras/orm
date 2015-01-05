<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MapperSelectionTest extends DataTestCase
{

	public function testToCollection()
	{
		$books = $this->orm->books->findBooksWithEvenId()->fetchPairs(NULL, 'id');
		Assert::same([2, 4], $books);
	}

}


$test = new MapperSelectionTest($dic);
$test->run();

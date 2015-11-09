<?php

/**
 * @testCase
 * @dataProvider ../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../bootstrap.php';


class BugTest extends DataTestCase
{

	public function testBug()
	{
		$author = $this->orm->authors->getById(2);

		$books = $author->books->get()->limitBy(1);
		foreach ($books as $book) {
			echo $book->publisher->name;
		}

		$books = $author->books;
		foreach ($books as $book) {
			$book->publisher->name;
		}

		Assert::true(TRUE);
	}

}


$test = new BugTest($dic);
$test->run();

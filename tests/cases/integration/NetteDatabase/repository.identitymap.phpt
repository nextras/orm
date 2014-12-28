<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DatabaseTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryIdentityMapTest extends DatabaseTestCase
{

	public function testPersistance()
	{
		$author = new Author();
		$author->name = 'A';

		$this->orm->authors->attach($author);

		$book = new Book();
		$book->title = 'B';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->authors->persistAndFlush($author);

		Assert::same($author->books->get()->fetch(), $book);
	}

}


$test = new RepositoryIdentityMapTest($dic);
$test->run();

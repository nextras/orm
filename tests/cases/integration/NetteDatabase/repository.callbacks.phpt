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
use NextrasTests\Orm\Publisher;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryCallbacksTest extends DatabaseTestCase
{

	public function testOnFlush()
	{
		$allFlush = [];
		$this->orm->onFlush[] = function(array $persisted, array $removed) use (&$allFlush) {
			foreach ($persisted as $persitedE) $allFlush[] = $persitedE;
			foreach ($removed as $removedE) $allFlush[] = $removedE;
		};

		$booksFlush = [];
		$this->orm->books->onFlush[] = function(array $persisted, array $removed) use (&$booksFlush) {
			foreach ($persisted as $persitedE) $booksFlush[] = $persitedE;
			foreach ($removed as $removedE) $booksFlush[] = $removedE;
		};

		$author = new Author();
		$author->name = 'Test';

		$this->orm->authors->persistAndFlush($author);
		Assert::same([$author], $allFlush);
		Assert::same([], $booksFlush);

		$publisher = new Publisher();
		$publisher->name = 'Pub';

		$book = new Book();
		$book->title = 'Book';
		$book->author = $author;
		$book->publisher = $publisher;

		$this->orm->books->persistAndFlush($book);

		Assert::same([$author, $book, $publisher], $allFlush);
		Assert::same([$book], $booksFlush);

		$this->orm->books->persistAndFlush($book);

		Assert::same([$author, $book, $publisher], $allFlush);
		Assert::same([$book], $booksFlush);
	}

}


$test = new RepositoryCallbacksTest($dic);
$test->run();

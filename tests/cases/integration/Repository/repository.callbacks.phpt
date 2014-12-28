<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryCallbacksTest extends TestCase
{

	public function testOnBeforePersist()
	{
		$author = new Author();
		$author->name = 'Test';

		$this->orm->authors->onBeforePersist[] = function(Author $author) {
			$book = new Book();
			$book->title = 'Test Book';
			$author->books->add($book);
		};

		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, $author->books->count());
		foreach ($author->books as $book) {
			Assert::true($book->isPersisted());
		}
	}

}


$test = new RepositoryCallbacksTest($dic);
$test->run();

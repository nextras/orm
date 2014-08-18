<?php

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\DatabaseTestCase;
use Nextras\Orm\Tests\Book;
use Tester\Assert;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';
Environment::lock('integration', TEMP_DIR);


/**
 * @testCase
 */
class NewHasOneAddTest extends DatabaseTestCase
{

	public function testAutoConnection()
	{
		$author1 = $this->orm->authors->getById(1);

		$book = new Book();
		$book->title = 'A new book';
		$author1->books->add($book);
		Assert::true($author1->books->has($book));
		Assert::same($book->author, $author1);


		$book = new Book();
		$book->title = 'The second new book';
		$book->author = $author1;
		Assert::true($author1->books->has($book));
		Assert::same($book->author, $author1);


		$author2 = $this->orm->authors->getById(2);
		$author2->books->add($book);
		Assert::false($author1->books->has($book));
		Assert::true($author2->books->has($book));
		Assert::same($book->author, $author2);


		$book->author = $author1;
		Assert::false($author2->books->has($book));
		Assert::true($author1->books->has($book));
		Assert::same($book->author, $author1);
	}


	public function testPersistanceHasOne()
	{
		$author = new Author();
		$author->name = 'Jon Snow';

		$book = new Book();
		$this->orm->books->attach($book);
		$book->title = 'A new book';
		$book->author = $author;

		$this->orm->books->persistAndFlush($book);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);
	}

}


$test = new NewHasOneAddTest($dic);
$test->run();

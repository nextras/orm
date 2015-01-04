<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Book;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class NewHasOneAddTest extends DataTestCase
{

	public function testAutoConnection()
	{
		$author1 = $this->orm->authors->getById(1);

		$book = new Book();
		$book->title = 'A new book';
		$book->publisher = $this->orm->publishers->getById(1);
		$author1->translatedBooks->add($book);
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);


		$book = new Book();
		$book->title = 'The second new book';
		$book->publisher = $this->orm->publishers->getById(1);
		$book->translator = $author1;
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);


		$author2 = $this->orm->authors->getById(2);
		$author2->translatedBooks->add($book);
		Assert::false($author1->translatedBooks->has($book));
		Assert::true($author2->translatedBooks->has($book));
		Assert::same($book->translator, $author2);


		$book->translator = $author1;
		Assert::false($author2->translatedBooks->has($book));
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);
	}


	public function testPersistanceHasOne()
	{
		$author = new Author();
		$author->name = 'Jon Snow';

		$book = new Book();
		$this->orm->books->attach($book);
		$book->title = 'A new book';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->books->persistAndFlush($book);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);
	}

}


$test = new NewHasOneAddTest($dic);
$test->run();

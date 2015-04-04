<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasManyRemoveTest extends DataTestCase
{

	public function testRemoveItem()
	{
		/** @var Author $author */
		$author = $this->orm->authors->getById(2);

		$book = $this->orm->books->getById(3);

		$author->translatedBooks->remove($book);
		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, $author->translatedBooks->count());
		Assert::same(1, $author->translatedBooks->countStored());
	}


	public function testRemoveCollection()
	{
		$author = new Author();
		$author->name = 'A';

		$this->orm->authors->attach($author);

		$book = new Book();
		$book->title = 'B';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->authors->persistAndFlush($author);

		foreach ($author->books as $book) {
			$this->orm->books->remove($book);
		}

		$this->orm->authors->persistAndFlush($author);
		Assert::same(0, $author->books->count());
	}

}


$test = new RelationshipOneHasManyRemoveTest($dic);
$test->run();

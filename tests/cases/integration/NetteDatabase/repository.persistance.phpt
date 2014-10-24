<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\DatabaseTestCase;
use Nextras\Orm\Tests\Publisher;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepostiroyPersistanceTest extends DatabaseTestCase
{

	public function testComplexPersistanceTree()
	{
		$authors = [];
		for ($i = 0; $i < 20; $i += 1) {
			$author = new Author();
			$author->name = 'Author ' . $i;
			$authors[] = $author;
		}

		$publishers = [];
		for ($i = 0; $i < 20; $i += 1) {
			$publisher = new Publisher();
			$publisher->name = 'Publisher ' . $i;
			$publishers[] = $publisher;
		}

		$books = [];
		for ($i = 0; $i < 20; $i += 1) {
			for ($y = 0; $y < 20; $y += 1) {
				$book = new Book();
				$this->orm->books->attach($book);
				$book->title = "Book $i-$y";

				$book->author = $authors[$i];
				$book->publisher = $publishers[$y];

				$books[] = $book;
			}
		}

		$this->orm->authors->persistAndFlush($authors[0]);

		foreach ($authors as $author) {
			Assert::true($author->isPersisted());
		}
		foreach ($publishers as $publisher) {
			Assert::true($publisher->isPersisted());
		}
		foreach ($books as $book) {
			Assert::true($book->isPersisted());
		}
	}

}


$test = new RepostiroyPersistanceTest($dic);
$test->run();

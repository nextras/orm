<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyPersistenceTest extends DataTestCase
{

	public function testPersiting()
	{
		$author1 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author1]);
		$author2 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author2]);
		$this->orm->authors->persist($author1);
		$this->orm->authors->persist($author2);
		$this->orm->authors->flush();

		$books = [];
		$authors = $this->orm->authors->findAll();
		foreach ($authors as $author) {
			foreach ($author->books as $book) {
				$book->title .= '#';
				$books[] = $book;
				Assert::true($book->isModified());
			}
			$this->orm->authors->persist($author);
		}

		foreach ($books as $book) {
			Assert::false($book->isModified());
		}

	}

}


$test = new RelationshipsOneHasManyPersistenceTest($dic);
$test->run();

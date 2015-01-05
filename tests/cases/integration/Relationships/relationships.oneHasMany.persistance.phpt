<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyPersistanceTest extends DataTestCase
{

	public function testPersiting()
	{
		$author1 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author1]);
		$author2 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author2]);
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


$test = new RelationshipsOneHasManyPersistanceTest($dic);
$test->run();

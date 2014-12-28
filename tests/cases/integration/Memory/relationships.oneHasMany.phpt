<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integrations;

use Mockery;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MemoryRelationshipOneHasManyTest extends TestCase
{

	public function testBasics()
	{
		$author1 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 1']);
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 2']);

		$author2 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author2, 'title' => 'Book 3']);
		$this->e('NextrasTests\Orm\Book', ['author' => $author2, 'title' => 'Book 4']);

		$author3 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author3, 'title' => 'Book 5']);
		$this->e('NextrasTests\Orm\Book', ['author' => $author3, 'title' => 'Book 6']);

		$this->orm->authors->persist($author1);
		$this->orm->authors->persist($author2);
		$this->orm->authors->persist($author3);
		$this->orm->flush();

		$books = [];
		foreach ($author1->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['Book 2', 'Book 1'], $books);

		$books = [];
		foreach ($author2->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['Book 4', 'Book 3'], $books);


		$books = [];
		foreach ($author3->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['Book 6', 'Book 5'], $books);
	}


	public function testFetchMethods()
	{
		$author1 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 1']);
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 2']);

		$this->orm->authors->persist($author1);
		$this->orm->flush();

		$book = $author1->books->get()->findBy(['title' => 'Book 2'])->fetch();
		Assert::same('Book 2', $book->title);
	}


	public function testDefaultOrderingOnEmptyCollection()
	{
		$author1 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 1', 'id' => 9]);
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 2', 'id' => 8]);
		$this->e('NextrasTests\Orm\Book', ['author' => $author1, 'title' => 'Book 2', 'id' => 10]);

		$ids = [];
		foreach ($author1->books as $book) {
			$ids[] = $book->id;
		}
		Assert::same([10, 9, 8], $ids);
	}

}


$test = new MemoryRelationshipOneHasManyTest($dic);
$test->run();


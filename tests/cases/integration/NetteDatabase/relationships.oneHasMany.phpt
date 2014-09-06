<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class RelationshipOneHasManyTest extends DatabaseTestCase
{

	public function testBasics()
	{
		$author = $this->orm->authors->getById(1);

		$collection = $author->books->get()->findBy(['title!' => 'Book 1']);
		Assert::equal(1, $collection->count());
		Assert::equal('Book 2', $collection->fetch()->title);

		$collection = $author->books->get()->findBy(['title!' => 'Book 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal('Book 1', $collection->fetch()->title);
		Assert::equal('Book 2', $collection->fetch()->title);
	}


	public function testRemove()
	{
		/** @var Author $author */
		$author = $this->orm->authors->getById(2);

		$book = $this->orm->books->getById(3);

		$author->translatedBooks->remove($book);
		$this->orm->authors->persistAndFlush($author);

		Assert::count(1, $author->translatedBooks);
	}


	public function testLimit()
	{
		$book = new Book();
		$this->orm->books->attach($book);
		$book->title = 'Book 5';
		$book->author = 1;
		$this->orm->books->persistAndFlush($book);

		$books = [];
		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');

		foreach ($authors as $author) {
			foreach ($author->books->get()->limitBy(2)->orderBy('title', ICollection::DESC) as $book) {
				$books[] = $book->id;
			}
		}

		Assert::same([5, 2, 4, 3], $books);
	}


	public function testEmptyEntityPreloadContainer()
	{
		$books = [];

		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');
		foreach ($authors as $author) {
			$author->setPreloadContainer(NULL);
			foreach ($author->books as $book) {
				$books[] = $book->id;
			}
		}

		Assert::same([5, 2, 1, 4, 3], $books);
	}

}


$test = new RelationshipOneHasManyTest($dic);
$test->run();

<?php

/**
 * @testCase
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

		Assert::same(1, $author->translatedBooks->count());
		Assert::same(1, $author->translatedBooks->countStored());
	}


	public function testRemove2()
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


	public function testLimit()
	{
		$book = new Book();
		$this->orm->books->attach($book);
		$book->title = 'Book 5';
		$book->author = 1;
		$book->publisher = 1;
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


	public function testCollectionCountWithLimit()
	{
		$author = $this->orm->authors->getById(1);
		$collection = $author->books->get();
		$collection = $collection->limitBy(1, 1);
		Assert::same(1, $collection->count());
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

		Assert::same([2, 1, 4, 3], $books);
	}


	public function testCachingBasic()
	{
		$author = $this->orm->authors->getById(1);
		$books = $author->books->get()->findBy(['translator' => NULL]);
		Assert::same(1, $books->count());

		$book = $books->fetch();
		$book->translator = $author;
		$this->orm->books->persistAndFlush($book);

		$books = $author->books->get()->findBy(['translator' => NULL]);
		Assert::same(0, $books->count());
	}

}


$test = new RelationshipOneHasManyTest($dic);
$test->run();

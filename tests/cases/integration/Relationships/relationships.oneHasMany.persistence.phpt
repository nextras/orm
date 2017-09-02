<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyPersistenceTest extends DataTestCase
{
	public function testPersiting()
	{
		$author1 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book XX']);
		$author2 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author2, 'title' => 'Book YY']);
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


	public function testRepeatedPersisting()
	{
		$publisher = new Publisher();
		$publisher->name = 'Jupiter Mining Corporation';

		$author = new Author();
		$author->name = 'Arnold Judas Rimmer';

		$book = new Book();
		$book->title = 'Better Than Life';
		$book->publisher = $publisher;
		$book->author = $author;

		$this->orm->persistAndFlush($author);
		Assert::false($book->isModified());

		$book->title = 'Backwards';
		$this->orm->persistAndFlush($author);
		Assert::false($book->isModified());
	}


	public function testCollectionState()
	{
		$publisher = new Publisher();
		$publisher->name = 'Jupiter Mining Corporation';

		$author = new Author();
		$author->name = 'Arnold Judas Rimmer';
		$this->orm->persistAndFlush($author);
		Assert::same([], iterator_to_array($author->books));

		$book = new Book();
		$book->title = 'Better Than Life';
		$book->author = $author;
		$book->publisher = $publisher;
		Assert::same([$book], iterator_to_array($author->books));

		$this->orm->persist($book);
		Assert::same([$book], iterator_to_array($author->books));

		$this->orm->flush();
		Assert::same([$book], iterator_to_array($author->books));
	}
}


$test = new RelationshipsOneHasManyPersistenceTest($dic);
$test->run();

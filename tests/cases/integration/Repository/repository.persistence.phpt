<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Exception\NullValueException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryPersistenceTest extends TestCase
{

	public function testComplexPersistenceTree(): void
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


	public function testOneHasOneDirected(): void
	{
		$author = new Author();
		$author->name = 'The Imp';
		$author->web = 'localhost';
		$author->born = new DateTimeImmutable('2000-01-01 12:12:12');

		$this->orm->authors->attach($author);

		$publisher = new Publisher();
		$publisher->name = 'Valyria';

		$book = new Book();
		$book->author = $author;
		$book->title = 'The Wall';
		$book->publisher = $publisher;
		$book->translator = $author;

		$book2 = new Book();
		$book2->author = $author;
		$book2->title = 'The Wall II';
		$book2->publisher = $publisher;
		$book2->previousPart = $book;

		$this->orm->authors->persistAndFlush($author);
		Assert::true($book->isPersisted());
		Assert::true($book2->isPersisted());
		Assert::same($book2, $book->nextPart);
	}


	public function testUnsettedNotNullProperty(): void
	{
		Assert::throws(function (): void {
			$author = new Author();
			$author->name = 'Author';
			$author->web = 'localhost';
			$this->orm->authors->attach($author);

			$book = new Book();
			$book->title = 'Title';
			$book->author = $author;

			$this->orm->books->persistAndFlush($book);
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$publisher is not nullable.');

	}


	public function testManyHasMany(): void
	{
		// create 10 tags
		for ($id = 0; $id < 10; $id++) {
			$tag = $this->e(Tag::class, [
				'name' => (string) $id,
			]);
			$this->orm->tags->persistAndFlush($tag);
		}

		// assign all tags to publisher
		$publisher = $this->e(Publisher::class, [
			'tags' => $this->orm->tags->findAll()->fetchAll(),
		]);
		$this->orm->publishers->persistAndFlush($publisher);

		// assign publisher and only one tag
		$book = $this->e(Book::class, [
			'publisher' => $publisher,
			'tags' => [
				$this->orm->tags->getBy([]),
			],
		]);

		// so one tag in book
		Assert::same(1, $book->tags->count());

		$this->orm->books->persistAndFlush($book);

		// after pesist, there is all tags, not just one!
		Assert::same(1, $book->tags->count());
	}

}


$test = new RepositoryPersistenceTest();
$test->run();

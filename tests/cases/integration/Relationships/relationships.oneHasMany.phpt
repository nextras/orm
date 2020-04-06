<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Nextras\Dbal\IConnection;
use Nextras\Dbal\UniqueConstraintViolationException;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasManyTest extends DataTestCase
{
	public function testBasics()
	{
		$author = $this->orm->authors->getById(1);

		$collection = $author->books->toCollection()->findBy(['title!=' => 'Book 1']);
		Assert::equal(1, $collection->count());
		Assert::equal(1, $collection->countStored());
		Assert::equal('Book 2', $collection->fetch()->title);

		$collection = $author->books->toCollection()->findBy(['title!=' => 'Book 3']);
		Assert::equal(2, $collection->count());
		Assert::equal(2, $collection->countStored());
		Assert::equal('Book 2', $collection->fetch()->title);
		Assert::equal('Book 1', $collection->fetch()->title);

		$collection = $author->books->toCollection()->resetOrderBy()->findBy(['title!=' => 'Book 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal(2, $collection->countStored());
		Assert::equal('Book 1', $collection->fetch()->title);
		Assert::equal('Book 2', $collection->fetch()->title);
	}


	public function testWithDifferentPrimaryKey()
	{
		$publisher = $this->orm->publishers->getById(1);
		$titles = [];
		foreach ($publisher->books as $book) {
			$titles[] = $book->title;
		}

		Assert::equal(['Book 1', 'Book 4'], $titles);
	}


	public function testRawValue()
	{
		$author = $this->orm->authors->getById(1);
		Assert::same([2, 1], $author->books->getRawValue());

		$this->orm->books->remove($this->orm->books->getById(1));
		Assert::same([2], $author->books->getRawValue());

		$book = new Book();
		$book->author = $author;
		$book->title = 'Test book';
		$book->publisher = 1;

		Assert::same([2], $author->books->getRawValue());

		$this->orm->books->persistAndFlush($book);

		Assert::same([5, 2], $author->books->getRawValue());
	}


	public function testPersistence()
	{
		$author1 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 1']);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 2']);

		$author2 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author2, 'title' => 'Book 3']);
		$this->e(Book::class, ['author' => $author2, 'title' => 'Book 4']);

		$author3 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author3, 'title' => 'Book 5']);
		$this->e(Book::class, ['author' => $author3, 'title' => 'Book 6']);

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


	public function testDefaultOrderingOnEmptyCollection()
	{
		$author1 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 1', 'id' => 9]);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 2', 'id' => 8]);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 2', 'id' => 10]);

		$ids = [];
		foreach ($author1->books as $book) {
			$ids[] = $book->id;
		}
		Assert::same([10, 9, 8], $ids);
	}


	public function testOrderingWithJoins()
	{
		$book = $this->orm->books->getById(1);
		$books = $book->translator->books->toCollection()->orderBy('ean->code')->fetchAll();
		Assert::count(2, $books);
	}


	public function testLimit()
	{
		$book = new Book();
		$this->orm->books->attach($book);
		$book->title = 'Book 5';
		$book->author = 1;
		$book->publisher = 1;
		$this->orm->books->persistAndFlush($book);

		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');

		$books = [];
		$counts = [];
		$countsStored = [];
		foreach ($authors as $author) {
			$booksLimited = $author->books->toCollection()->limitBy(2)->resetOrderBy()->orderBy('title', ICollection::DESC);
			foreach ($booksLimited as $book) {
				$books[] = $book->id;
			}
			$counts[] = $booksLimited->count();
			$countsStored[] = $booksLimited->countStored();
		}

		Assert::same([5, 2, 4, 3], $books);
		Assert::same([2, 2], $counts);
		Assert::same([2, 2], $countsStored);
	}


	public function testEmptyEntityPreloadContainer()
	{
		$books = [];

		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');
		foreach ($authors as $author) {
			$author->setPreloadContainer(null);
			foreach ($author->books as $book) {
				$books[] = $book->id;
			}
		}

		Assert::same([2, 1, 4, 3], $books);
	}


	public function testCachingBasic()
	{
		$author = $this->orm->authors->getById(1);
		$books = $author->books->toCollection()->findBy(['translator' => null]);
		Assert::same(1, $books->count());

		$book = $books->fetch();
		$book->translator = $author;
		$this->orm->books->persistAndFlush($book);

		$books = $author->books->toCollection()->findBy(['translator' => null]);
		Assert::same(0, $books->count());
	}


	public function testUniqueConstraintException()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Only for DbalMapper');
		}

		$tag = $this->orm->tags->getById(2);
		foreach ([2, 1] as $tagFollowerId) {
			try {
				$tagFollower = new TagFollower();
				$tagFollower->tag = $tag;
				$tagFollower->author = $tagFollowerId;
				$this->orm->tagFollowers->persistAndFlush($tagFollower, false);
				Assert::true($tagFollower->isPersisted());
			} catch (UniqueConstraintViolationException $e) {
				$this->orm->tagFollowers->getMapper()->rollback();
				Assert::false($tagFollower->isPersisted());
			}
		}

		$connection = $this->container->getByType(IConnection::class);
		$pairs = $connection->query('SELECT author_id FROM tag_followers WHERE tag_id = 2 ORDER BY author_id')->fetchPairs(null, 'author_id');
		Assert::same([1, 2], $pairs);

		$this->orm->refreshAll(true);

		$ids = [];
		foreach ($tag->tagFollowers as $tagFollower) {
			$ids[] = $tagFollower->author->id;
			Assert::true($tagFollower->isPersisted());
		}
		sort($ids);
		Assert::same([1, 2], $ids);
	}


	public function testCountAfterRemoveAndFlushAndCount()
	{
		$author = new Author();
		$author->name = 'The Imp';
		$author->web = 'localhost';
		$author->born = '2000-01-01 12:12:12';

		$this->orm->authors->attach($author);

		$publisher = new Publisher();
		$publisher->name = 'Valyria';

		$book = new Book();
		$book->author = $author;
		$book->title = 'The Wall';
		$book->publisher = $publisher;
		$book->translator = $author;

		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, \count($author->books));

		foreach ($author->books as $book) {
			$this->orm->books->remove($book);
		}

		Assert::same(0, \count($author->books));

		$this->orm->books->flush();

		Assert::same(0, \count($author->books));

		$book3 = new Book();
		$book3->author = $author;
		$book3->title = 'The Wall III';
		$book3->publisher = $publisher;

		Assert::same(1, \count($author->books));

		$this->orm->books->persist($book3);

		Assert::same(1, \count($author->books));
	}


	public function testCountStoredOnOneHasManyRelationshipCondition()
	{
		$publisher = $this->orm->publishers->getByIdChecked(1);
		$books = $publisher->books->toCollection()->findBy([
			'tags->id' => 1,
		]);
		Assert::same(1, $books->countStored());

		$books = $publisher->books->toCollection()->findBy([
			ICollection::OR,
			'title' => 'Book 1',
			'tags->id' => 1,
		]);
		Assert::same(1, $books->countStored());
	}
}


$test = new RelationshipOneHasManyTest($dic);
$test->run();

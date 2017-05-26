<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Nextras\Orm\Relationships\OneHasMany;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyCollectionTest extends DataTestCase
{

	/** @var Publisher */
	private $publisher;

	/** @var Author */
	private $authorA;

	/** @var Author */
	private $authorB;

	/** @var OneHasMany|Book[] */
	private $books;


	protected function setUp()
	{
		parent::setUp();

		$this->orm->clear();
		$this->publisher = $this->orm->publishers->getById(1);
		$this->authorA = $this->orm->authors->getById(1);
		$this->authorB = $this->orm->authors->getById(2);
		$this->books = $this->authorA->books;
	}


	public function testAddA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->books)); // SELECT
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(4, $queries);
		}
	}


	public function testAddB()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			Assert::count(3, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries);
		}
	}


	public function testAddC()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			Assert::count(3, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(3, iterator_to_array($this->books));
		});

		if ($queries) {
			Assert::count(5, $queries);
		}
	}


	public function testAddD()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // INSERT
			Assert::count(4, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(6, $queries);
		}
	}


	public function testAddE()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(2, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // INSERT
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(6, $queries);
		}
	}


	public function testAddF()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // INSERT
			Assert::count(2, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries);
		}
	}


	public function testAddH()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook()); // intentionally no checks after first add()
			$this->books->add($this->createBook());
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // BEGIN + INSERT + INSERT
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(6, $queries);
		}
	}


	public function testAddI()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(2, $this->books->getEntitiesForPersistence());

			// intentionally no checks after first add()
			$this->books->add($this->createBook());
			// intentionally no checks after first persist()
			$this->orm->persist($this->authorA); // BEGIN + INSERT
			$this->books->add($this->createBook());
			Assert::count(4, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA); // INSERT
			Assert::count(4, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(7, $queries);
		}
	}


	public function testFetchExistingA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$bookA = $this->getExistingBook(1); // THIS FIRES UNNECESSARY QUERY: SELECT * FROM authors WHERE id IN (1)
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$bookB = $this->getExistingBook(2); // THIS FIRES UNNECESSARY QUERY: SELECT * FROM authors WHERE id IN (1)
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(4, $queries); // SELECT one, SELECT its author, SELECT all, SELECT 2 book's author
		}
	}


	public function testFetchDerivedCollectionA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->books->get()->fetchAll(); // SELECT
			Assert::count(3, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(1, $queries);
		}
	}


	public function testFetchDerivedCollectionB()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->get()->limitBy(1)->fetchAll();
			if ($this->section === Helper::SECTION_ARRAY) {
				// array collection loads the book relationship during filtering the related books
				Assert::count(2, $this->books->getEntitiesForPersistence());
			} else {
				// one book from releationship
				Assert::count(1, $this->books->getEntitiesForPersistence());
			}
		});

		if ($queries) {
			Assert::count(1, $queries);
		}
	}


	public function testRemoveA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$bookA = $this->getExistingBook(1); // THIS FIRES UNNECESSARY QUERY: SELECT * FROM authors WHERE id IN (1)
			$bookA->author = $this->authorB;
			Assert::count(1, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence()); // one tracked + one to remove

			$this->orm->persist($bookA);
			$this->orm->persist($this->authorA);
			Assert::count(1, iterator_to_array($this->books));
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(1, iterator_to_array($this->books));
			Assert::count(1, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(6, $queries); // SELECT all, SELECT 1 book's author, BEGIN, UPDATE, SELECT, COMMIT
		}
	}


	public function testRemoveB()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$book2 = $this->orm->books->getById(2); // SELECT book

			// 5 SELECTS: all relationships (author, books_x_tags, tags, books.next_part, publisher)
			// TRANSATION BEGIN
			// 2 DELETES: books_x_tags, book
			$this->orm->books->remove($book2);
			Assert::false($this->books->isModified());
		});

		if ($queries) {
			Assert::count(9, $queries);
		}
	}


	public function testRemoveC()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$book2 = $this->orm->books->getById(2); // SELECT
			$book2->author; // SELECT
			Assert::count(1, $this->books->getEntitiesForPersistence());

			// 4 SELECTS: all rest relationships (books_x_tags, tags, books.next_part, publisher)
			// TRANSATION BEGI
			// 2 DELETES: books_x_tags, book
			$this->orm->books->remove($book2);
			Assert::count(0, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(9, $queries);
		}
	}


	public function testRemoveD()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->authorA->translatedBooks->getEntitiesForPersistence());

			$book1 = $this->orm->books->getById(1); // SELECT
			Assert::count(0, $this->authorA->translatedBooks->getEntitiesForPersistence());

			iterator_to_array($this->authorA->translatedBooks); // SELECT ALL
			$this->authorA->translatedBooks->remove($book1);
			Assert::count(1, $this->authorA->translatedBooks->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(2, $queries);
		}
	}


	private function createBook()
	{
		static $id = 0;

		$book = new Book();
		$book->title = 'New Book #' . (++$id);
		$book->publisher = $this->publisher;

		return $book;
	}


	private function getExistingBook($id)
	{
		$book = $this->orm->books->getById($id);
		Assert::type(Book::class, $book);
		Assert::same($this->authorA, $book->author);

		return $book;
	}
}


$test = new RelationshipsOneHasManyCollectionTest($dic);
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Orm\Relationships\OneHasMany;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


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
		$this->publisher = $this->orm->publishers->getByIdChecked(1);
		$this->authorA = $this->orm->authors->getByIdChecked(1);
		$this->authorB = $this->orm->authors->getByIdChecked(2);
		$this->books = $this->authorA->books;
	}


	public function testAddA(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(4, $queries);
		}
	}


	public function testAddB(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(5, $queries);
		}
	}


	public function testAddC(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(5, $queries);
		}
	}


	public function testAddD(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(6, $queries);
		}
	}


	public function testAddE(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(6, $queries);
		}
	}


	public function testAddF(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(5, $queries);
		}
	}


	public function testAddH(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(6, $queries);
		}
	}


	public function testAddI(): void
	{
		$queries = $this->getQueries(function (): void {
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

		if ($queries !== null) {
			Assert::count(7, $queries);
		}
	}


	public function testFetchExistingA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			// SELECT BOOK + SELECT AUTHOR
			$bookA = $this->getExistingBook(1); // THIS FIRES UNNECESSARY QUERY: SELECT * FROM authors WHERE id IN (1)
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$bookB = $this->getExistingBook(2);
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(3, $queries);
		}
	}


	public function testFetchDerivedCollectionA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->books->toCollection()->fetchAll(); // SELECT
			Assert::count(3, $this->books->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(1, $queries);
		}
	}


	public function testFetchDerivedCollectionB(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->toCollection()->limitBy(1)->fetchAll();
			if ($this->section === Helper::SECTION_ARRAY) {
				// array collection loads the book relationship during filtering the related books
				Assert::count(2, $this->books->getEntitiesForPersistence());
			} else {
				// one book from relationship
				Assert::count(1, $this->books->getEntitiesForPersistence());
			}
		});

		if ($queries !== null) {
			Assert::count(1, $queries);
		}
	}


	public function testRemoveA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$bookA = $this->getExistingBook(1); // THIS FIRES UNNECESSARY QUERY: SELECT * FROM authors WHERE id IN (1)
			$bookA->author = $this->authorB;
			Assert::count(1, iterator_to_array($this->books)); // SELECT ALL without Book#1
			Assert::count(2, $this->books->getEntitiesForPersistence()); // one tracked + one to remove

			$this->orm->persist($bookA); // BEGIN, UPDATE
			$this->orm->persist($this->authorA); // noop
			Assert::count(1, iterator_to_array($this->books)); // SELECT ALL
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(1, iterator_to_array($this->books));
			Assert::count(1, $this->books->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(7, $queries);
		}
	}


	public function testRemoveB(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$book2 = $this->orm->books->getByIdChecked(2); // SELECT book

			// 5 SELECTS: all relationships (author, books_x_tags, tags, books.next_part, publisher)
			// TRANSACTION BEGIN
			// 2 DELETES: books_x_tags, book
			$this->orm->books->remove($book2);
			Assert::false($this->books->isModified());
		});

		if ($queries !== null) {
			Assert::count(9, $queries);
		}
	}


	public function testRemoveC(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$book2 = $this->orm->books->getByIdChecked(2); // SELECT
			$book2->getValue('author'); // SELECT
			Assert::count(1, $this->books->getEntitiesForPersistence());

			// 4 SELECTS: all rest relationships (books_x_tags, tags, books.next_part, publisher)
			// TRANSACTION BEGIN
			// 2 DELETES: books_x_tags, book
			$this->orm->books->remove($book2);
			Assert::count(0, $this->books->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(9, $queries);
		}
	}


	public function testRemoveD(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->authorA->translatedBooks->getEntitiesForPersistence());

			$book1 = $this->orm->books->getByIdChecked(1); // SELECT
			Assert::count(0, $this->authorA->translatedBooks->getEntitiesForPersistence());

			iterator_to_array($this->authorA->translatedBooks); // SELECT ALL
			$this->authorA->translatedBooks->remove($book1);
			Assert::count(1, $this->authorA->translatedBooks->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(2, $queries);
		}
	}


	public function testReAdd(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->authorA->books->getEntitiesForPersistence());
			iterator_to_array($this->authorA->books); // SELECT ALL
			Assert::count(2, $this->authorA->books->getEntitiesForPersistence());
			$book = $this->authorA->books->toCollection()->fetch();
			Assert::notNull($book);
			$this->authorA->books->remove($book);
			$this->authorA->books->add($book);
			Assert::count(2, $this->authorA->books->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(1, $queries);
		}
	}


	private function createBook(): Book
	{
		static $id = 0;

		$book = new Book();
		$book->title = 'New Book #' . (++$id);
		$book->publisher = $this->publisher;

		return $book;
	}


	private function getExistingBook(int $id): Book
	{
		$book = $this->orm->books->getByIdChecked($id);
		Assert::type(Book::class, $book);
		Assert::same($this->authorA, $book->author);

		return $book;
	}
}


$test = new RelationshipsOneHasManyCollectionTest();
$test->run();

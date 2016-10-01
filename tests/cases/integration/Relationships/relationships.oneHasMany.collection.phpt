<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use Nextras\Dbal\Connection;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\OneHasMany;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use Tester\Assert;
use Tester\Environment;

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

		$this->orm->clearIdentityMapAndCaches(IModel::I_KNOW_WHAT_I_AM_DOING);
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

			$this->orm->persist($this->authorA);
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(4, $queries); // BEGIN, INSERT, SELECT, COMMIT
		}
	}


	public function testAddB()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(4, $queries); // BEGIN, INSERT, SELECT, COMMIT
		}
	}


	public function testAddC()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(3, iterator_to_array($this->books));
		});

		if ($queries) {
			Assert::count(4, $queries); // SELECT, BEGIN, INSERT, COMMIT
		}
	}


	public function testAddD()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(1, $this->books->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->books));
			Assert::count(3, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(4, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries); // BEGIN, INSERT, SELECT, INSERT, COMMIT
		}
	}


	public function testAddE()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(2, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries); // BEGIN, INSERT, INSERT, SELECT, COMMIT
		}
	}


	public function testAddF()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook());
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(2, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries); // BEGIN, INSERT, INSERT, SELECT, COMMIT
		}
	}


	public function testAddH()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook()); // intentionally no checks after first add()
			$this->books->add($this->createBook());
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries); // SELECT, BEGIN, INSERT, INSERT, COMMIT
		}
	}


	public function testAddI()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$this->books->add($this->createBook()); // intentionally no checks after first add()
			$this->orm->persist($this->authorA);    // intentionally no checks after first persist()
			$this->books->add($this->createBook());
			Assert::count(4, $this->books->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->persist($this->authorA);
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(4, iterator_to_array($this->books));
			Assert::count(4, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries); // SELECT, BEGIN, INSERT, INSERT, COMMIT
		}
	}


	public function testFetchExistingA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->books->getEntitiesForPersistence());

			$bookA = $this->getExistingBook(1);
			Assert::count(1, $this->books->getEntitiesForPersistence()); // FAILS, this->books has no knowledge of the book
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());

			$bookB = $this->getExistingBook(2);
			Assert::count(2, iterator_to_array($this->books));
			Assert::count(2, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(2, $queries); // SELECT one, SELECT all
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
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->persist($bookA);
			Assert::count(1, iterator_to_array($this->books));
			Assert::count(1, $this->books->getEntitiesForPersistence());

			$this->orm->flush();
			Assert::count(1, iterator_to_array($this->books));
			Assert::count(1, $this->books->getEntitiesForPersistence());
		});

		if ($queries) {
			dump($queries);
			Assert::count(4, $queries); // SELECT all, BEGIN, UPDATE, COMMIT
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


	private function getQueries(callable $callback)
	{
		$conn = $this->container->getByType(Connection::class, FALSE);

		if (!$conn) {
			$callback();
			return [];
		}

		$queries = [];
		$conn->onQuery[__CLASS__] = function ($conn, $sql) use (& $queries) {
			if (strpos($sql, 'pg_catalog') === FALSE) {
				$queries[] = $sql;
				echo $sql, "\n";
			}
		};

		try {
			$callback();
			return $queries;

		} finally {
			unset($conn->onQuery[__CLASS__]);
		}
	}

}


$test = new RelationshipsOneHasManyCollectionTest($dic);
$test->run();

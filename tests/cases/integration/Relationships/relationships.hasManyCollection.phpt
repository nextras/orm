<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Nextras\Orm\Mapper\Dbal\DbalCollection;
use Nextras\Orm\Relationships\HasManyCollection;
use Nextras\Orm\Relationships\OneHasMany;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsHasManyCollectionTest extends DataTestCase
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


	public function testSelect()
	{
		$queries = $this->getQueries(function () {
			$collection = $this->books->get();
			Assert::type(DbalCollection::class, $collection);
			Assert::same(2, iterator_count($this->books));
			Assert::count(2, $collection);

			$this->books->add($this->createBook());
			$collection = $this->books->get();
			Assert::type(HasManyCollection::class, $collection);
			Assert::same(3, iterator_count($this->books));
			Assert::count(3, $collection);

			$this->orm->persist($this->authorA);
			$this->orm->flush(); // COMMIT

			$collection = $this->books->get();
			Assert::type(DbalCollection::class, $collection);
			Assert::same(3, iterator_count($this->books));
			Assert::count(3, $collection);
		});

		if ($queries) {
			Assert::count(5, $queries);
		}
	}


	public function testFindBy()
	{
		$queries = $this->getQueries(function () {
			$collection = $this->books->get()->findBy(['publisher' => $this->publisher]);
			Assert::type(DbalCollection::class, $collection);
			Assert::same(1, iterator_count($collection));
			Assert::count(1, $collection);

			$this->books->add($this->createBook());
			$collection = $this->books->get()->findBy(['publisher' => $this->publisher]);
			Assert::type(HasManyCollection::class, $collection);
			Assert::same(2, iterator_count($collection));
			Assert::count(2, $collection);

			$collection = $this->books->get()->findBy(['publisher' => $this->publisher])->findBy(['id' => 1]);
			Assert::type(HasManyCollection::class, $collection);
			Assert::same(1, iterator_count($collection));
			Assert::count(1, $collection);

			$this->orm->persist($this->authorA);
			$this->orm->flush(); // COMMIT

			$collection = $this->books->get()->findBy(['publisher' => $this->publisher]);
			Assert::type(DbalCollection::class, $collection);
			Assert::same(2, iterator_count($collection));
			Assert::count(2, $collection);
		});

		if ($queries) {
			Assert::count(7, $queries);
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


$test = new RelationshipsHasManyCollectionTest($dic);
$test->run();

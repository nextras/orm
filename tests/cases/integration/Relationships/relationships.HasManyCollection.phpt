<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\HasManyCollection;
use Nextras\Orm\Relationships\OneHasMany;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use Tester\Assert;
use function array_map;


require_once __DIR__ . '/../../../bootstrap.php';


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

	/** @var string */
	private $baseCollectionClass;


	protected function setUp()
	{
		parent::setUp();

		$this->orm->clear();
		$this->publisher = $this->orm->publishers->getByIdChecked(1);
		$this->authorA = $this->orm->authors->getByIdChecked(1);
		$this->authorB = $this->orm->authors->getByIdChecked(2);
		$this->books = $this->authorA->books;

		if ($this->section === Helper::SECTION_ARRAY) {
			$this->baseCollectionClass = ArrayCollection::class;
		} else {
			$this->baseCollectionClass = DbalCollection::class;
		}
	}


	public function testSelect(): void
	{
		$queries = $this->getQueries(function (): void {
			$collection = $this->books->toCollection();
			Assert::type($this->baseCollectionClass, $collection);
			Assert::same(2, iterator_count($this->books)); // SELECT
			Assert::count(2, $collection);
			Assert::same(2, $collection->countStored()); // SELECT COUNT

			$this->books->add($this->createBook());
			$collection = $this->books->toCollection();
			Assert::type(HasManyCollection::class, $collection);
			Assert::same(3, iterator_count($this->books)); // without SELECT, DBAL's relationship cache
			Assert::count(3, $collection);
			Assert::same(3, $collection->countStored()); // without SELECT, DBAL's relationship cache

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			$this->orm->flush(); // COMMIT

			$collection = $this->books->toCollection();
			Assert::type($this->baseCollectionClass, $collection);
			Assert::same(3, iterator_count($this->books)); // SELECT
			Assert::count(3, $collection);
			Assert::same(3, $collection->countStored()); // SELECT COUNT
		});

		if ($queries !== null) {
			Assert::count(7, $queries);
		}
	}


	public function testFindBy(): void
	{
		$queries = $this->getQueries(function (): void {
			$collection = $this->books->toCollection()->findBy(['publisher' => $this->publisher]);
			Assert::type($this->baseCollectionClass, $collection);
			Assert::same(1, iterator_count($collection));  // SELECT
			Assert::count(1, $collection);
			Assert::same(1, $collection->countStored()); // SELECT COUNT

			$this->books->add($this->createBook());
			$collection = $this->books->toCollection()->findBy(['publisher' => $this->publisher]);
			Assert::type(HasManyCollection::class, $collection);
			Assert::same(2, iterator_count($collection)); // useless load of Publisher for comparison in final collection;
			Assert::count(2, $collection);
			Assert::same(2, $collection->countStored()); // without SELECT, DBAL's relationship cache

			$collection = $this->books->toCollection()->findBy(['publisher' => $this->publisher])->findBy(['id' => 1]);
			Assert::type(HasManyCollection::class, $collection);
			Assert::same(1, iterator_count($collection)); // SELECT
			Assert::count(1, $collection);
			Assert::same(1, $collection->countStored()); // SELECT COUNT

			$this->orm->persist($this->authorA); // BEGIN + INSERT
			$this->orm->flush(); // COMMIT

			$collection = $this->books->toCollection()->findBy(['publisher' => $this->publisher]);
			Assert::type($this->baseCollectionClass, $collection);
			Assert::same(2, iterator_count($collection)); // SELECT
			Assert::count(2, $collection);
			Assert::same(2, $collection->countStored()); // SELECT COUNT
		});

		if ($queries !== null) {
			Assert::count(10, $queries);
		}
	}


	public function testGetBy(): void
	{
		$book = new Book();
		$book->title = 'test';
		$this->orm->books->attach($book);

		$author = new Author();
		$author->books->add($book);

		Assert::null($author->books->toCollection()->getBy(['title' => 'another']));
		Assert::same($book, $author->books->toCollection()->getBy(['title' => 'test']));
	}


	public function testFindByRemove(): void
	{
		$book = $this->orm->books->getByIdChecked(3);
		$this->authorB->translatedBooks->remove($book);

		Assert::count(1, $this->authorB->translatedBooks);

		$books = $this->authorB->translatedBooks->toCollection()->orderBy('id')->limitBy(1);

		Assert::type(HasManyCollection::class, $books);
		Assert::count(1, $books);

		$bookIds = array_map(function ($book): ?int {
			return $book->id;
		}, $books->fetchAll());
		Assert::same([4], $bookIds);
	}


	private function createBook(): Book
	{
		static $id = 0;

		$book = new Book();
		$book->title = 'New Book #' . (++$id);
		$book->publisher = $this->publisher;

		return $book;
	}
}


$test = new RelationshipsHasManyCollectionTest();
$test->run();

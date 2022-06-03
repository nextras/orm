<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\ManyHasMany;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Tag;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsManyHasManyCollectionTest extends DataTestCase
{
	/** @var Book */
	private $book;

	/** @var ManyHasMany<Tag> */
	private $tags;


	protected function setUp()
	{
		parent::setUp();

		$this->orm->clear();
		$this->book = $this->orm->books->getByIdChecked(1);
		$this->tags = $this->book->tags;
	}


	public function testAddA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT TAG + INSERT JOIN
			Assert::count(1, $this->tags->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(3, iterator_to_array($this->tags));
			Assert::count(3, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(6, $queries);
		}
	}


	public function testAddB(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT TAG + INSERT JOIN
			Assert::count(3, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(3, iterator_to_array($this->tags));
			Assert::count(3, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(8, $queries);
		}
	}


	public function testAddC(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(2, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(3, iterator_to_array($this->tags));
			Assert::count(3, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT TAG + INSERT JOIN
			Assert::count(3, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(3, iterator_to_array($this->tags));
		});

		if ($queries !== null) {
			Assert::count(8, $queries);
		}
	}


	public function testAddD(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT JOIN + INSERT TAG
			Assert::count(1, $this->tags->getEntitiesForPersistence());
			Assert::count(3, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // INSERT JOIN + INSERT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->tags));
			Assert::count(4, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(10, $queries);
		}
	}


	public function testAddE(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT JOIN + INSERT TAG
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(2, $this->tags->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // INSERT TAG + INSERT JOIN
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->tags));
			Assert::count(4, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(10, $queries);
		}
	}


	public function testAddF(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT TAG + INSERT JOIN
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(2, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // INSERT TAG + INSERT JOIN
			Assert::count(2, $this->tags->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->tags));
			Assert::count(4, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(8, $queries);
		}
	}


	public function testAddH(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(2, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag()); // intentionally no checks after first add()
			$this->tags->add($this->createTag());
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // BEGIN + INSERT TAG + INSERT TAG + INSERT JOIN
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->tags));
			Assert::count(4, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(9, $queries);
		}
	}


	public function testAddI(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(2, $this->tags->getEntitiesForPersistence());

			// intentionally no checks after first add()
			$this->tags->add($this->createTag());
			// intentionally no checks after first persist()
			$this->orm->persist($this->book); // BEGIN + INSERT TAG + INSERT JOIN
			$this->tags->add($this->createTag());
			Assert::count(4, $this->tags->getEntitiesForPersistence());
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->persist($this->book); // INSERT TAG + INSERT JOIN
			Assert::count(4, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(4, $this->tags->getEntitiesForPersistence());

			$this->orm->flush(); // COMMIT
			Assert::count(4, iterator_to_array($this->tags));
			Assert::count(4, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(12, $queries);
		}
	}


	public function testFetchExistingA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$tagA = $this->getExistingTag(1); // SELECT TAG + SELECT JOIN + SELECT BOOK
			Assert::count(1, $this->tags->getEntitiesForPersistence());
			Assert::count(2, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(2, iterator_to_array($this->tags));
			Assert::count(2, $this->tags->getEntitiesForPersistence());

			$tagB = $this->getExistingTag(2); // SELECT JOIN + SELECT BOOKS
			Assert::count(2, iterator_to_array($this->tags)); // SELECT JOIN + SELECT TAG
			Assert::count(2, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(9, $queries);
		}
	}


	public function testFetchDerivedCollectionA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->tags->toCollection()->fetchAll(); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(2, $queries);
		}
	}


	public function testFetchDerivedCollectionB(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->toCollection()->orderBy('id')->limitBy(1)->fetchAll(); // SELECT JOIN + SELECT TAG
			// one book from releationship
			Assert::count(1, $this->tags->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(2, $queries);
		}
	}


	public function testRemoveA(): void
	{
		$queries = $this->getQueries(function (): void {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$tag2 = $this->orm->tags->getByIdChecked(2); // SELECT

			// 6 SELECTS: all relationships (tag_followers, books_x_tags, tags (???), publishers_x_tags, authors)
			// TRANSACTION BEGIN
			// 4 DELETES: 2 books_x_tags, tag_follower, tag
			$this->orm->tags->remove($tag2);
			Assert::false($this->tags->isModified());
		});

		if ($queries !== null) {
			Assert::count(12, $queries);
		}
	}


	public function testRemoveB(): void
	{
		$queries = $this->getQueries(function (): void {
			$book2 = $this->orm->books->getByIdChecked(2); // SELECT
			$book3 = $this->orm->books->getByIdChecked(3); // SELECT

			$tag = $this->orm->tags->getByIdChecked(1); // SELECT
			$property = $tag->getProperty('books');
			Assert::type(HasMany::class, $property);
			Assert::count(0, $property->getEntitiesForPersistence());
			$tag->setBooks($book2, $book3, $book2); // SELECT JOIN + SELECT BOOK
			$property = $tag->getProperty('books');
			Assert::type(HasMany::class, $property);
			Assert::count(3, $property->getEntitiesForPersistence());
		});

		if ($queries !== null) {
			Assert::count(5, $queries);
		}
	}


	private function createTag(): Tag
	{
		static $id = 0;

		$tag = new Tag();
		$tag->setName('New Tag #' . (++$id));
		return $tag;
	}


	private function getExistingTag(int $id): Tag
	{
		$tag = $this->orm->tags->getByIdChecked($id);
		Assert::type(Tag::class, $tag);
		foreach ($tag->books as $book) {
			if ($this->book === $book) {
				return $tag;
			}
		}

		Assert::fail('At least one bug has to had a tag=1.');
	}
}


$test = new RelationshipsManyHasManyCollectionTest();
$test->run();

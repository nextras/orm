<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Nextras\Orm\Relationships\ManyHasMany;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Tag;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsManyHasManyCollectionTest extends DataTestCase
{
	/** @var Book */
	private $book;

	/** @var ManyHasMany|Tag[] */
	private $tags;


	protected function setUp()
	{
		parent::setUp();

		$this->orm->clear();
		$this->book = $this->orm->books->getById(1);
		$this->tags = $this->book->tags;
	}


	public function testAddA()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(6, $queries);
		}
	}


	public function testAddB()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(8, $queries);
		}
	}


	public function testAddC()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(8, $queries);
		}
	}


	public function testAddD()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(10, $queries);
		}
	}


	public function testAddE()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(10, $queries);
		}
	}


	public function testAddF()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(8, $queries);
		}
	}


	public function testAddH()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(9, $queries);
		}
	}


	public function testAddI()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(12, $queries);
		}
	}


	public function testFetchExistingA()
	{
		$queries = $this->getQueries(function () {
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

		if ($queries) {
			Assert::count(9, $queries);
		}
	}


	public function testFetchDerivedCollectionA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->add($this->createTag());
			Assert::count(1, $this->tags->getEntitiesForPersistence());

			$this->tags->get()->fetchAll(); // SELECT JOIN + SELECT TAG
			Assert::count(3, $this->tags->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(2, $queries);
		}
	}


	public function testFetchDerivedCollectionB()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$this->tags->get()->orderBy('id')->limitBy(1)->fetchAll(); // SELECT JOIN + SELECT TAG
			// one book from releationship
			Assert::count(1, $this->tags->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(2, $queries);
		}
	}


	public function testRemoveA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$tag2 = $this->orm->tags->getById(2); // SELECT

			// 5 SELECTS: all relationships (tag_followers, books_x_tags, tags (???), authors)
			// TRANSATION BEGIN
			// 4 DELETES: 2 books_x_tags, tag_follower, tag
			$this->orm->tags->remove($tag2);
			Assert::false($this->tags->isModified());
		});

		if ($queries) {
			Assert::count(11, $queries);
		}
	}


	public function testRemoveB()
	{
		$queries = $this->getQueries(function () {
			$book2 = $this->orm->books->getById(2); // SELECT
			$book3 = $this->orm->books->getById(3); // SELECT

			$tag = $this->orm->tags->getById(1); // SELECT
			Assert::count(0, $tag->books->getEntitiesForPersistence());
			$tag->books->set([$book2, $book3]); // SELECT JOIN + SELECT BOOK
			Assert::count(3, $tag->books->getEntitiesForPersistence());
		});

		if ($queries) {
			Assert::count(5, $queries);
		}
	}


	private function createTag()
	{
		static $id = 0;

		$tag = new Tag();
		$tag->name = 'New Tag #' . (++$id);
		return $tag;
	}


	private function getExistingTag($id)
	{
		$tag = $this->orm->tags->getById($id);
		Assert::type(Tag::class, $tag);
		foreach ($tag->books as $book) {
			if ($this->book === $book) {
				return $tag;
			}
		}

		Assert::fail('At least one bug has to had a tag=1.');
	}
}


$test = new RelationshipsManyHasManyCollectionTest($dic);
$test->run();

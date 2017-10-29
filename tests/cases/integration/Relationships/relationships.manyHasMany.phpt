<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Nextras\Dbal\IConnection;
use Nextras\Dbal\UniqueConstraintViolationException;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\TagFollower;
use NextrasTests\Orm\User;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasManyTest extends DataTestCase
{
	public function testCache()
	{
		$book = $this->orm->books->getById(1);

		$collection = $book->tags->get()->findBy(['name!=' => 'Tag 1'])->orderBy('id');
		Assert::equal(1, $collection->count());
		Assert::equal(1, $collection->countStored());
		Assert::equal('Tag 2', $collection->fetch()->name);

		$collection = $book->tags->get()->findBy(['name!=' => 'Tag 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal(2, $collection->countStored());
		Assert::equal('Tag 1', $collection->fetch()->name);
		Assert::equal('Tag 2', $collection->fetch()->name);
	}


	public function testLimit()
	{
		$book = $this->orm->books->getById(1);
		$book->tags->add(3);
		$this->orm->books->persistAndFlush($book);

		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');

		$tags = [];
		$counts = [];
		$countsStored = [];
		foreach ($books as $book) {
			$limitedTags = $book->tags->get()->limitBy(2)->orderBy('name', ICollection::DESC);
			foreach ($limitedTags as $tag) {
				$tags[] = $tag->id;
			}
			$counts[] = $limitedTags->count();
			$countsStored[] = $limitedTags->countStored();
		}

		Assert::same([3, 2, 3, 2, 3], $tags);
		Assert::same([2, 2, 1, 0], $counts);
		Assert::same([2, 2, 1, 0], $countsStored);
	}


	public function testEmptyPreloadContainer()
	{
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');
		$tags = [];

		foreach ($books as $book) {
			$book->setPreloadContainer(null);
			foreach ($book->tags->get()->orderBy('name') as $tag) {
				$tags[] = $tag->id;
			}
		}

		Assert::same([1, 2, 2, 3, 3], $tags);
	}


	public function testRemove()
	{
		$book = $this->orm->books->getById(1);
		$tag = $this->orm->tags->getById(1);
		$book->tags->remove($tag);
		$this->orm->books->persistAndFlush($book);

		Assert::same(1, $book->tags->count());
		Assert::same(1, $book->tags->countStored());
	}


	public function testCollectionCountWithLimit()
	{
		$book = $this->orm->books->getById(1);
		$collection = $book->tags->get();
		$collection = $collection->orderBy('id')->limitBy(1, 1);
		Assert::same(1, $collection->count());
	}


	public function testRawValue()
	{
		$book = $this->orm->books->getById(1);
		Assert::same([1, 2], $book->tags->getRawValue());

		$book->tags->remove(1);
		Assert::same([2], $book->tags->getRawValue());

		$tag = new Tag();
		$tag->name = 'Test tag';
		$tag->books->add($book);

		Assert::same([2], $book->tags->getRawValue());

		$this->orm->tags->persistAndFlush($tag);

		Assert::same([2, 4], $book->tags->getRawValue());

		$book->tags->setRawValue([]);
		Assert::same([], $book->tags->getRawValue());

		$this->orm->tags->persistAndFlush($tag);

		Assert::same([], $book->tags->getRawValue());
	}


	public function testCaching()
	{
		$book = $this->orm->books->getById(1);
		$tags = $book->tags->get()->findBy(['name' => 'Tag 1']);
		Assert::same(1, $tags->count());

		$tag = $tags->fetch();
		$tag->name = 'XXX';
		$this->orm->tags->persistAndFlush($tag);

		$tags = $book->tags->get()->findBy(['name' => 'Tag 1']);
		Assert::same(0, $tags->count());
	}


	public function testCachingPreload()
	{
		// init caches
		$books = $this->orm->books->findAll();
		foreach ($books as $book) {
			iterator_to_array($book->tags);
		}

		$book = $this->orm->books->getById(2);

		Assert::false($book->tags->has(1));
		Assert::true($book->tags->has(2));
		Assert::true($book->tags->has(3));
		Assert::false($book->tags->has(4));
	}


	public function testIsModified()
	{
		$tag = new Tag('A');
		$book = $this->orm->books->getById(1);
		$book->tags->add($tag);

		Assert::true($book->tags->isModified());
		Assert::true($tag->books->isModified());

		$tag = $this->orm->tags->getById(1);
		$book->tags->remove($tag);

		Assert::true($book->tags->isModified());
		Assert::true($tag->books->isModified());
	}


	public function testSelfReferencing()
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			// An explicit value for the identity column in table 'users' can only be specified when a column list is used and IDENTITY_INSERT is ON.
			// http://stackoverflow.com/questions/2148091/syntax-for-inserting-into-a-table-with-no-values
			Environment::skip('Inserting dummy rows when no arguments are passed is not supported.');
		}

		$userA = new User();
		$this->orm->persistAndFlush($userA);

		$userB = new User();
		$userB->myFriends->add($userA);

		$this->orm->persistAndFlush($userB);
		Assert::same(1, $userA->friendsWithMe->count());
		Assert::same(0, $userA->myFriends->count());
	}


	public function testRepeatedPersisting()
	{
		$tagA = new Tag('A');
		$tagB = new Tag('B');

		$book = $this->orm->books->getById(1);
		$book->tags->add($tagA);
		$book->tags->add($tagB);

		$this->orm->persistAndFlush($book);
		Assert::false($tagA->isModified());
		Assert::false($tagB->isModified());

		$tagA->name = 'X';
		$this->orm->persistAndFlush($book);
		Assert::false($tagA->isModified());
		Assert::false($tagB->isModified());
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
			} catch (UniqueConstraintViolationException $e) {
				$this->orm->tagFollowers->getMapper()->rollback();
			}
		}

		$connection = $this->container->getByType(IConnection::class);
		$pairs = $connection->query('SELECT author_id FROM tag_followers WHERE tag_id = 2 ORDER BY author_id')->fetchPairs(null, 'author_id');
		Assert::same([1, 2], $pairs);
	}


	public function testCountStoredOnManyToManyCondition()
	{
		$books = $this->orm->books->findBy(['this->tags->name' => 'Tag 2']);
		Assert::same(2, $books->countStored());
	}
}


$test = new RelationshipManyHasManyTest($dic);
$test->run();

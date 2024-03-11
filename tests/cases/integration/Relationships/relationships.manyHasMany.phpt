<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Relationships\HasMany;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\User;
use Tester\Assert;
use function count;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasManyTest extends DataTestCase
{
	public function testCache(): void
	{
		$book = $this->orm->books->getByIdChecked(1);

		$collection = $book->tags->toCollection()->findBy(['name!=' => 'Tag 1'])->orderBy('id');
		Assert::equal(1, $collection->count());
		Assert::equal(1, $collection->countStored());
		Assert::equal('Tag 2', $collection->fetchChecked()->name);

		$collection = $book->tags->toCollection()->findBy(['name!=' => 'Tag 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal(2, $collection->countStored());
		Assert::equal('Tag 1', $collection->fetchChecked()->name);
		Assert::equal('Tag 2', $collection->fetchChecked()->name);
	}


	public function testLimit(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->tags->add(3);
		$this->orm->books->persistAndFlush($book);

		$books = $this->orm->books->findAll()->orderBy('id');

		$tags = [];
		$counts = [];
		$countsStored = [];
		foreach ($books as $innerBook) {
			$limitedTags = $innerBook->tags->toCollection()->limitBy(2)->orderBy('name', ICollection::DESC);
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


	public function testEmptyPreloadContainer(): void
	{
		$books = $this->orm->books->findAll()->orderBy('id');
		$tags = [];

		foreach ($books as $book) {
			$book->setPreloadContainer(null);
			foreach ($book->tags->toCollection()->orderBy('name') as $tag) {
				$tags[] = $tag->id;
			}
		}

		Assert::same([1, 2, 2, 3, 3], $tags);
	}


	public function testRemove(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$tag = $this->orm->tags->getByIdChecked(1);
		$book->tags->remove($tag);
		$this->orm->books->persistAndFlush($book);

		Assert::same(1, $book->tags->count());
		Assert::same(1, $book->tags->countStored());
	}


	public function testCollectionCountWithLimit(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$collection = $book->tags->toCollection();
		$collection = $collection->orderBy('id')->limitBy(1, 1);
		Assert::same(1, $collection->count());
	}


	public function testRawValue(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		Assert::same([1, 2], $book->tags->getRawValue());

		$book->tags->remove(1);
		Assert::same([2], $book->tags->getRawValue());

		$tag = new Tag();
		$tag->setName('Test tag');
		$property = $tag->getProperty('books');
		Assert::type(HasMany::class, $property);
		$property->add($book);

		Assert::same([2], $book->tags->getRawValue());

		$this->orm->tags->persistAndFlush($tag);

		Assert::same([2, 4], $book->tags->getRawValue());

		$book->tags->setRawValue([]);
		Assert::same([], $book->tags->getRawValue());

		$this->orm->tags->persistAndFlush($tag);

		Assert::same([], $book->tags->getRawValue());
	}


	public function testCaching(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$tags = $book->tags->toCollection()->findBy(['name' => 'Tag 1']);
		Assert::same(1, $tags->count());

		$tag = $tags->fetchChecked();
		$tag->setName('XXX');
		$this->orm->tags->persistAndFlush($tag);

		$tags = $book->tags->toCollection()->findBy(['name' => 'Tag 1']);
		Assert::same(0, $tags->count());
	}


	public function testCachingPreload(): void
	{
		// init caches
		$books = $this->orm->books->findAll();
		foreach ($books as $book) {
			iterator_to_array($book->tags);
		}

		$book = $this->orm->books->getByIdChecked(2);

		Assert::false($book->tags->has(1));
		Assert::true($book->tags->has(2));
		Assert::true($book->tags->has(3));
		Assert::false($book->tags->has(4));
	}


	public function testIsModified(): void
	{
		$tag = new Tag('A');
		$book = $this->orm->books->getByIdChecked(1);
		$book->tags->add($tag);

		Assert::true($book->tags->isModified());
		$property = $tag->getProperty('books');
		Assert::type(HasMany::class, $property);
		Assert::true($property->isModified());

		$tag = $this->orm->tags->getByIdChecked(1);
		$book->tags->remove($tag);

		Assert::true($book->tags->isModified());
		$property = $tag->getProperty('books');
		Assert::type(HasMany::class, $property);
		Assert::true($property->isModified());
	}


	public function testSelfReferencing(): void
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			$connection = $this->container->getByType(IConnection::class);
			$connection->query('SET IDENTITY_INSERT users ON;');
		}

		$userA = new User();
		$userA->id = 123;
		$this->orm->persistAndFlush($userA);

		$userB = new User();
		$userB->id = 124;
		$userB->myFriends->add($userA);

		$this->orm->persistAndFlush($userB);
		Assert::same(1, $userA->friendsWithMe->count());
		Assert::same(0, $userA->myFriends->count());
	}


	public function testRepeatedPersisting(): void
	{
		$tagA = new Tag('A');
		$tagB = new Tag('B');

		$book = $this->orm->books->getByIdChecked(1);
		$book->tags->add($tagA);
		$book->tags->add($tagB);

		$this->orm->persistAndFlush($book);
		Assert::false($tagA->isModified());
		Assert::false($tagB->isModified());

		$tagA->setName('X');
		$this->orm->persistAndFlush($book);
		Assert::false($tagA->isModified());
		Assert::false($tagB->isModified());
	}


	public function testCountStoredOnManyToManyCondition(): void
	{
		$books = $this->orm->books->findBy(['tags->name' => 'Tag 2']);
		Assert::same(2, $books->countStored());
	}


	public function testJoinAcrossDifferentPaths(): void
	{
		$books = $this->orm->books->findBy(
			[
				ICollection::OR,
				'tags->name' => 'Tag 1',
				'nextPart->tags->name' => 'Tag 3',
			]
		)->orderBy('id');
		Assert::same([1, 4], $books->fetchPairs(null, 'id'));

		$tag5 = new Tag('Tag 5');
		$book4 = $this->orm->books->getByIdChecked(4);
		$book4->tags->add($tag5);
		$this->orm->persistAndFlush($tag5);

		$books = $this->orm->books->findBy(
			[
				ICollection::AND,
				'tags->name' => 'Tag 5',
				'nextPart->tags->name' => 'Tag 3',
			]
		)->orderBy('id');
		Assert::same([4], $books->fetchPairs(null, 'id'));
	}


	public function testCountAfterRemoveAndFlushAndCount(): void
	{
		$book = new Book();
		$book->author = $this->orm->authors->getByIdChecked(1);
		$book->title = 'The Wall';
		$book->publisher = 1;
		$book->translator = 1;

		$tag = new Tag('Testing Tag');
		$property = $tag->getProperty('books');
		Assert::type(HasMany::class, $property);
		$property->add($book);

		$this->orm->tags->persistAndFlush($tag);

		Assert::same(1, count($tag->books));

		foreach ($tag->books as $innerBook) {
			$this->orm->books->remove($innerBook);
		}

		Assert::same(0, count($tag->books));

		$this->orm->books->flush();

		Assert::same(0, count($tag->books));

		$book3 = new Book();
		$book3->author = $this->orm->authors->getByIdChecked(1);
		$book3->title = 'The Wall III';
		$book3->publisher = 1;
		$book3->tags->add($tag);

		Assert::same(1, count($tag->books));

		$this->orm->books->persist($book3);

		Assert::same(1, count($tag->books));
	}


	public function testCountStoredOnManyHasManyRelationshipCondition(): void
	{
		$tag = $this->orm->tags->getByIdChecked(1);
		$books = $tag->books->findBy([
			'author->id' => 1,
		]);
		Assert::same(1, $books->count());
		Assert::same(1, $books->countStored());

		$books = $tag->books->findBy([
			'author->tagFollowers->author->id' => 1,
		]);
		if ($this->section !== Helper::SECTION_MSSQL) {
			$books = $books->orderBy('title');
		}
		Assert::same(1, $books->count());
		Assert::same(1, $books->countStored());
	}


	public function testSymmetricRelationship(): void
	{
		$tag = $this->orm->tags->getByIdChecked(2);
		$property = $tag->getProperty('books');
		Assert::type(HasMany::class, $property);
		$property->set([1, 2]);

		$book = $this->orm->books->getByIdChecked(1);
		Assert::count(1, $book->tags->getEntitiesForPersistence());
	}
}


$test = new RelationshipManyHasManyTest();
$test->run();

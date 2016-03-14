<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Tag;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasManyTest extends DataTestCase
{

	public function testCache()
	{
		$book = $this->orm->books->getById(1);

		$collection = $book->tags->get()->findBy(['name!' => 'Tag 1'])->orderBy('id');
		Assert::equal(1, $collection->count());
		Assert::equal(1, $collection->countStored());
		Assert::equal('Tag 2', $collection->fetch()->name);

		$collection = $book->tags->get()->findBy(['name!' => 'Tag 3'])->orderBy('id');
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
			$book->setPreloadContainer(NULL);
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
		$collection = $collection->limitBy(1, 1);
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

}


$test = new RelationshipManyHasManyTest($dic);
$test->run();

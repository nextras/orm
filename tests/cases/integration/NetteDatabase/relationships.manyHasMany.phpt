<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasManyTest extends DataTestCase
{

	public function testCache()
	{
		$book = $this->orm->books->getById(1);

		$collection = $book->tags->get()->findBy(['name!' => 'Tag 1'])->orderBy('id');
		Assert::equal(1, $collection->count());
		Assert::equal('Tag 2', $collection->fetch()->name);

		$collection = $book->tags->get()->findBy(['name!' => 'Tag 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal('Tag 1', $collection->fetch()->name);
		Assert::equal('Tag 2', $collection->fetch()->name);
	}


	public function testLimit()
	{
		$book = $this->orm->books->getById(1);
		$book->tags->add(3);
		$this->orm->books->persistAndFlush($book);

		$tags = [];
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');

		foreach ($books as $book) {
			foreach ($book->tags->get()->limitBy(2)->orderBy('name', ICollection::DESC) as $tag) {
				$tags[] = $tag->id;
			}
		}

		Assert::same([3, 2, 3, 2, 3], $tags);
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

}


$test = new RelationshipManyHasManyTest($dic);
$test->run();

<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class RelationshipManyHasMany1Test extends DatabaseTestCase
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

}


$test = new RelationshipManyHasMany1Test($dic);
$test->run();


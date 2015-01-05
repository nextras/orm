<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionTest extends DataTestCase
{

	public function testCountOnLimited()
	{
		$collection = $this->orm->books->findAll();
		$collection = $collection->limitBy(1, 1);
		Assert::same(1, $collection->count());

		$collection = $collection->limitBy(1, 10);
		Assert::same(0, $collection->count());
	}


	public function testOrdering()
	{
		$books = $this->orm->books->findAll()
			->orderBy('this->author->id', ICollection::DESC)
			->orderBy('title', ICollection::ASC);

		$ids = [];
		foreach ($books as $book) {
			$ids[] = $book->id;
		}

		Assert::same([3, 4, 1, 2], $ids);


		$books = $this->orm->books->findAll()
			->orderBy('this->author->id', ICollection::DESC)
			->orderBy('title', ICollection::DESC);

		$ids = [];
		foreach ($books as $book) {
			$ids[] = $book->id;
		}
		Assert::same([4, 3, 2, 1], $ids);
	}

}


$test = new CollectionTest($dic);
$test->run();

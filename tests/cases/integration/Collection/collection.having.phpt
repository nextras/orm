<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionHavingTest extends DataTestCase
{
	public function testHavingWithSameNamedColumnsInGroupBy(): void
	{
		// this is a test especially for MySQL and Orm's workaround
		$books = $this->orm->books->findBy([
			ICollection::OR,
			'tags->id' => 1, // Book #1
			'author->name' => 'Writer 2', // Book #3, #4
			'publisher->name' => 'Nextras publisher C', // Book #3
		]);
		Assert::same($books->count(), 3);
	}
}


$test = new CollectionHavingTest();
$test->run();

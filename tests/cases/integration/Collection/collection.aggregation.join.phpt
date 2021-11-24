<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\Aggregations\AnyAggregator;
use Nextras\Orm\Collection\Aggregations\NoneAggregator;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAggregationJoinTest extends DataTestCase
{
	public function testAny(): void
	{
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			new AnyAggregator(),
			['books->title' => 'Book 1'],
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
		$author = $authors->fetch();
		Assert::notNull($author);
		Assert::same(1, $author->id);

		// implicit any
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			['books->title' => 'Book 1'],
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
		$author = $authors->fetch();
		Assert::notNull($author);
		Assert::same(1, $author->id);
	}


	public function testNone(): void
	{
		$authors = $this->orm->authors->findBy([
			ICollection::AND,
			new NoneAggregator(),
			['books->title' => 'Book 1'],
		]);
		Assert::same(1, $authors->count());
		Assert::same(1, $authors->countStored());
		$author = $authors->fetch();
		Assert::notNull($author);
		Assert::same(2, $author->id);
	}
}


(new CollectionAggregationJoinTest())->run();

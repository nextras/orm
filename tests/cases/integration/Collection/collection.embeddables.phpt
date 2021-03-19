<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\Functions\MaxAggregateFunction;
use Nextras\Orm\Collection\Functions\MinAggregateFunction;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Exception\InvalidArgumentException;
use NextrasTests\Orm\Currency;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Money;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionEmbeddablesTest extends DataTestCase
{
	public function testBasics(): void
	{
		$books1 = $this->orm->books->findBy(['price->cents>=' => 1000]);
		Assert::same(0, $books1->count());
		Assert::same(0, $books1->countStored());

		$book = $this->orm->books->getByIdChecked(1);
		$book->price = new Money(1000, Currency::CZK());
		$this->orm->persistAndFlush($book);

		$books2 = $this->orm->books->findBy(['price->cents>=' => 1000]);
		Assert::same(1, $books2->count());
		Assert::same(1, $books2->countStored());
	}


	public function testOrderBy(): void
	{
		$books = $this->orm->books->findAll()->orderBy('price->cents');
		$bookIds = $books->fetchPairs(null, 'id');
		Assert::same([3, 1, 2, 4], $bookIds);

		$author = $this->orm->authors->getByIdChecked(1);
		$books = $author->books->toCollection()->orderBy('price->cents', ICollection::DESC);
		$bookIds = $books->fetchPairs(null, 'id');
		Assert::same([2, 1], $bookIds);

		$authors = $this->orm->authors->findAll()->orderBy([
			MaxAggregateFunction::class,
			'books->price->cents',
		]);
		$authorIds = $authors->fetchPairs(null, 'id');
		Assert::same([1, 2], $authorIds);

		$authors = $this->orm->authors->findAll()->orderBy([
			MinAggregateFunction::class,
			'books->price->cents',
		]);
		$authorIds = $authors->fetchPairs(null, 'id');
		Assert::same([2, 1], $authorIds);
	}


	public function testInvalidExpression(): void
	{
		Assert::throws(function (): void {
			$this->orm->authors->findBy(['books->price' => 20])->fetchAll();
		}, InvalidArgumentException::class, 'Property expression \'books->price\' does not fetch specific property.');
	}
}


$test = new CollectionEmbeddablesTest();
$test->run();

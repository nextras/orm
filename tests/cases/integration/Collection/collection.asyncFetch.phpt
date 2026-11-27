<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Async\AsyncQuery;
use Nextras\Orm\Async\CollectionFetcher;
use Nextras\Orm\Async\IQueryExecutor;
use Nextras\Orm\Async\SequentialQueryExecutor;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Exception\NotSupportedException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAsyncFetchTest extends DataTestCase
{
	public function testSetFetchedRowsSkipsQuery(): void
	{
		$collection = $this->getDbalCollection($this->orm->books->findAll()->orderBy('id'));
		Assert::false($collection->isFetched());

		$collection->setFetchedRows($this->fetchRows($collection->getConnection(), $collection->getFetchQuery()));
		Assert::true($collection->isFetched());

		$ids = [];
		$queries = $this->getQueries(function () use ($collection, &$ids): void {
			foreach ($collection as $book) {
				$ids[] = $book->id;
			}
		});
		Assert::same([1, 2, 3, 4], $ids);
		Assert::same([], $queries ?? []);
	}


	public function testSetFetchedCountSkipsQuery(): void
	{
		$collection = $this->getDbalCollection($this->orm->books->findBy(['author' => 1]));
		Assert::false($collection->isCountFetched());

		$query = $collection->getCountQuery();
		$rows = $this->fetchRows($collection->getConnection(), $query);
		$collection->setFetchedCount((int) $rows[0]['count']);
		Assert::true($collection->isCountFetched());

		$count = null;
		$queries = $this->getQueries(function () use ($collection, &$count): void {
			$count = $collection->countStored();
		});
		Assert::same(2, $count);
		Assert::same([], $queries ?? []);
	}


	public function testRelationshipCollectionIsNotSupported(): void
	{
		$collection = $this->getDbalCollection($this->orm->authors->getByIdChecked(1)->books->toCollection());
		Assert::exception(fn() => $collection->getFetchQuery(), NotSupportedException::class);
		Assert::exception(fn() => $collection->getCountQuery(), NotSupportedException::class);
	}


	public function testFetcherReturnsCollectionsAndCountsUnderSameKeys(): void
	{
		$executor = new class implements IQueryExecutor {
			/** @var list<list<array-key>> */
			public array $calls = [];


			public function executeAll(IConnection $connection, array $queries): array
			{
				$this->calls[] = array_keys($queries);
				return (new SequentialQueryExecutor())->executeAll($connection, $queries);
			}
		};

		$filtered = $this->orm->books->findBy(['author' => 1])->orderBy('id');
		$fetcher = new CollectionFetcher($executor);
		$result = $fetcher->fetchAll([
			'items' => $filtered->limitBy(1),
			'total' => CollectionFetcher::count($filtered),
			'authors' => $this->orm->authors->findAll()->orderBy('id'),
		]);

		Assert::same(['items', 'total', 'authors'], array_keys($result));
		Assert::same(2, $result['total']->get());

		$bookIds = $authorIds = [];
		$queries = $this->getQueries(function () use ($result, &$bookIds, &$authorIds): void {
			$bookIds = array_map(fn($book) => $book->id, $result['items']->fetchAll());
			$authorIds = array_map(fn($author) => $author->id, $result['authors']->fetchAll());
		});
		Assert::same([1], $bookIds);
		Assert::same([1, 2], $authorIds);
		Assert::same([], $queries ?? []);

		if ($this->section === Helper::SECTION_ARRAY) {
			Assert::same([], $executor->calls);
		} else {
			Assert::same([['items', 'total', 'authors']], $executor->calls);
		}
	}


	public function testFetcherSharesIdentityMap(): void
	{
		$fetcher = new CollectionFetcher();
		[$a, $b] = $fetcher->fetchAll([
			$this->orm->books->findBy(['id' => 1]),
			$this->orm->books->findBy(['id' => 1]),
		]);
		Assert::same($a->fetchChecked(), $b->fetchChecked());
	}


	public function testFetcherSkipsAlreadyFetched(): void
	{
		$books = $this->orm->books->findAll();
		$total = $books->countStored();
		$items = $books->fetchAll();

		$count = CollectionFetcher::count($books);
		$queries = $this->getQueries(function () use ($books, $count): void {
			(new CollectionFetcher())->fetchAll([$books, $count]);
		});
		Assert::same([], $queries ?? []);
		Assert::same($total, $count->get());
		Assert::same($items, $books->fetchAll());
	}


	/**
	 * @param mixed $collection
	 * @return DbalCollection<Book>
	 */
	private function getDbalCollection($collection): DbalCollection
	{
		if ($this->section === Helper::SECTION_ARRAY) Environment::skip('DBAL only.');
		assert($collection instanceof DbalCollection);
		return $collection;
	}


	/**
	 * @return list<array<string, mixed>>
	 */
	private function fetchRows(IConnection $connection, AsyncQuery $query): array
	{
		$rows = [];
		foreach ($connection->queryArgs($query->sql, $query->args) as $row) {
			$rows[] = $row->toArray();
		}
		return $rows;
	}
}


$test = new CollectionAsyncFetchTest();
$test->run();

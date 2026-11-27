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
	public function testPreparedFetchSkipsQuery(): void
	{
		$collection = $this->getDbalCollection($this->orm->books->findAll()->orderBy('id'));
		$fetch = $collection->prepareAsyncFetch();
		assert($fetch !== null);
		$fetch->apply($this->fetchRows($fetch->connection, $fetch->query));
		Assert::null($collection->prepareAsyncFetch());

		$ids = [];
		$queries = $this->getQueries(function () use ($collection, &$ids): void {
			foreach ($collection as $book) {
				$ids[] = $book->id;
			}
		});
		Assert::same([1, 2, 3, 4], $ids);
		Assert::same([], $queries ?? []);
	}


	public function testPreparedCountSkipsQuery(): void
	{
		$collection = $this->getDbalCollection($this->orm->books->findBy(['author' => 1]));
		$fetch = $collection->prepareAsyncCount();
		assert($fetch !== null);
		$fetch->apply($this->fetchRows($fetch->connection, $fetch->query));
		Assert::null($collection->prepareAsyncCount());

		$count = null;
		$queries = $this->getQueries(function () use ($collection, &$count): void {
			$count = $collection->countStored();
		});
		Assert::same(2, $count);
		Assert::same([], $queries ?? []);
	}


	public function testPreparedFetchIsDroppedWhenCollectionChanged(): void
	{
		$collection = $this->getDbalCollection($this->orm->books->findAll()->orderBy('id'));
		$fetch = $collection->prepareAsyncFetch();
		$count = $collection->prepareAsyncCount();
		assert($fetch !== null && $count !== null);
		$rows = $this->fetchRows($fetch->connection, $fetch->query);
		$countRows = $this->fetchRows($count->connection, $count->query);

		$collection->getQueryBuilder()->andWhere('%column = %i', 'id', 3); // e.g. by an entity hook in between
		$fetch->apply($rows);
		$count->apply($countRows);

		Assert::same([3], array_map(fn($book) => $book->id, $collection->fetchAll()));
		Assert::same(1, $collection->countStored());
	}


	public function testRelationshipCollectionIsNotSupported(): void
	{
		$collection = $this->getDbalCollection($this->orm->authors->getByIdChecked(1)->books->toCollection());
		Assert::exception(fn() => $collection->prepareAsyncFetch(), NotSupportedException::class);
		Assert::exception(fn() => $collection->prepareAsyncCount(), NotSupportedException::class);
	}


	public function testModifiedRelationshipCollectionIsNotSupported(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		$author->books->remove($this->orm->books->getByIdChecked(1));
		$collection = $author->books->toCollection();

		$fetcher = new CollectionFetcher();
		Assert::exception(fn() => $fetcher->fetchAll([$collection]), NotSupportedException::class);
		Assert::exception(fn() => $fetcher->fetchAll([CollectionFetcher::count($collection)]), NotSupportedException::class);
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
			Assert::same([[0, 1, 2]], $executor->calls);
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


	public function testFetcherRequestsTheSameCollectionOnce(): void
	{
		$executor = new class implements IQueryExecutor {
			public int $queries = 0;


			public function executeAll(IConnection $connection, array $queries): array
			{
				$this->queries += count($queries);
				return (new SequentialQueryExecutor())->executeAll($connection, $queries);
			}
		};

		$books = $this->orm->books->findAll();
		$result = (new CollectionFetcher($executor))->fetchAll([
			'a' => $books,
			'b' => $books,
			'c' => CollectionFetcher::count($books),
			'd' => CollectionFetcher::count($books),
		]);
		Assert::same($this->section === Helper::SECTION_ARRAY ? 0 : 2, $executor->queries);
		Assert::same(4, $result['c']->get());
		Assert::same(4, $result['d']->get());
	}


	public function testFetcherCountsLimitedCollection(): void
	{
		$limited = $this->orm->books->findAll()->orderBy('id')->limitBy(2, 1);
		$expected = (clone $limited)->countStored();

		['total' => $total] = (new CollectionFetcher())->fetchAll(['total' => CollectionFetcher::count($limited)]);
		Assert::same(2, $expected);
		Assert::same($expected, $total->get());
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

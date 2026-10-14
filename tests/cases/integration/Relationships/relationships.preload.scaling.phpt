<?php declare(strict_types = 1);

/**
 * Loading cost must scale linearly with the number of loaded entities (no per-parent O(n) work while traversing
 * preloaded relationships, which makes the whole traversal O(n^2)).
 *
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Dbal\IConnection;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsPreloadScalingTest extends DataTestCase
{
	private const BOOKS_PER_AUTHOR = 2;
	private const SMALL = 1000;
	private const LARGE = 8000;
	/** Linear scaling gives ratio ~ LARGE / SMALL = 8, quadratic ~ 64; allow a generous noise margin. */
	private const MAX_RATIO = 14.0;
	private const FIRST_AUTHOR_ID = 1000;
	private const FIRST_BOOK_ID = 10000;


	/** Huge preloading IN (...) queries are not worth snapshotting. */
	protected bool $snapshotQueries = false;

	/** Consumes loaded values so the traversal is not optimized away / reported as unused. */
	private int $sink = 0;


	protected function setUpData(): void
	{
		parent::setUpData();
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Scaling is measured on Dbal mappers only.');
		}

		$authors = [];
		for ($i = 0; $i < intdiv(self::LARGE, self::BOOKS_PER_AUTHOR); $i++) {
			$authors[] = ['id' => self::FIRST_AUTHOR_ID + $i, 'name' => "A$i", 'web' => 'http://example.com', 'born_on' => null];
		}
		$this->insertRows('authors', $authors);

		$books = [];
		for ($i = 0; $i < self::LARGE; $i++) {
			$books[] = [
				'id' => self::FIRST_BOOK_ID + $i,
				'author_id' => self::FIRST_AUTHOR_ID + intdiv($i, self::BOOKS_PER_AUTHOR),
				'title' => "B$i",
				'publisher_id' => 1,
				'published_at' => '2021-12-31 23:59:59',
				'genre' => 'fantasy',
			];
		}
		$this->insertRows('books', $books);
	}


	/**
	 * Inserts rows with explicit ids in chunks (SQL Server allows at most 1000 rows per INSERT).
	 * @param literal-string $table
	 * @param list<array<string, mixed>> $rows
	 */
	private function insertRows(string $table, array $rows): void
	{
		$connection = $this->container->getByType(IConnection::class);
		$isMssql = $this->section === Helper::SECTION_MSSQL;
		if ($isMssql) {
			$connection->query('SET IDENTITY_INSERT %table ON', $table);
		}
		foreach (array_chunk($rows, 500) as $chunk) {
			$connection->query('INSERT INTO %table %values[]', $table, $chunk);
		}
		if ($isMssql) {
			$connection->query('SET IDENTITY_INSERT %table OFF', $table);
		}
	}


	public function testManyHasOneTraversalScalesLinearly(): void
	{
		$measure = function (int $n): float {
			return $this->measureBest(function () use ($n): void {
				$books = $this->orm->books->findBy(['id>=' => self::FIRST_BOOK_ID])->orderBy('id')->limitBy($n);
				foreach ($books as $book) {
					$this->sink += strlen($book->author->name);
				}
			});
		};

		$this->assertLinear($measure(self::SMALL), $measure(self::LARGE));
	}


	public function testOneHasManyTraversalScalesLinearly(): void
	{
		$measure = function (int $n): float {
			return $this->measureBest(function () use ($n): void {
				$authors = $this->orm->authors->findBy(['id>=' => self::FIRST_AUTHOR_ID])
					->orderBy('id')
					->limitBy(intdiv($n, self::BOOKS_PER_AUTHOR));
				foreach ($authors as $author) {
					foreach ($author->books as $book) {
						$this->sink += strlen($book->title);
					}
				}
			});
		};

		$this->assertLinear($measure(self::SMALL), $measure(self::LARGE));
	}


	public function testOneHasManyTraversalMemoryScalesLinearly(): void
	{
		$measure = function (int $n): float {
			$this->orm->clear();
			gc_collect_cycles();
			$before = memory_get_usage();
			$authors = $this->orm->authors->findBy(['id>=' => self::FIRST_AUTHOR_ID])
				->orderBy('id')
				->limitBy(intdiv($n, self::BOOKS_PER_AUTHOR))
				->fetchAll();
			foreach ($authors as $author) {
				foreach ($author->books as $book) {
					$this->sink += strlen($book->title);
				}
			}
			$used = memory_get_usage() - $before;
			unset($authors, $author, $book);
			$this->orm->clear();
			return (float) $used;
		};

		$this->assertLinear($measure(self::SMALL), $measure(self::LARGE));
	}


	private function measureBest(callable $callback): float
	{
		$best = INF;
		for ($i = 0; $i < 3; $i++) {
			$this->orm->clear();
			$start = hrtime(true);
			$callback();
			$best = min($best, (float) (hrtime(true) - $start));
		}
		$this->orm->clear();
		return $best;
	}


	private function assertLinear(float $small, float $large): void
	{
		Assert::true($this->sink > 0);
		$ratio = $large / $small;
		Assert::true(
			$ratio < self::MAX_RATIO,
			sprintf('cost ratio for %d vs %d entities is %.1f (small %.0f, large %.0f)', self::LARGE, self::SMALL, $ratio, $small, $large),
		);
	}
}


$test = new RelationshipsPreloadScalingTest();
$test->run();

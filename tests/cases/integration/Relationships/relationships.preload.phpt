<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


/**
 * Locks in relationship preloading through collection iterators (and the memoized relationship cache keys).
 */
class RelationshipsPreloadTest extends DataTestCase
{
	public function testManyHasOnePreloadsOnce(): void
	{
		$this->orm->clear();
		$authors = [];
		$queries = $this->getQueries(function () use (&$authors): void {
			foreach ($this->orm->books->findAll()->orderBy('id') as $book) {
				$authors[] = $book->author->id;
				$authors[] = $book->author->id; // repeated access
			}
		});
		Assert::same([1, 1, 1, 1, 2, 2, 2, 2], $authors);
		if ($queries !== null) {
			Assert::count(2, $queries); // books + one preload of all authors
		}
	}


	public function testInterleavedCollections(): void
	{
		$this->orm->clear();
		$pairs = [];
		$outer = $this->orm->books->findBy(['id<=' => 2])->orderBy('id');
		$inner = $this->orm->books->findBy(['id>' => 2])->orderBy('id');
		foreach ($outer as $bookA) {
			foreach ($inner as $bookB) {
				$pairs[] = [$bookA->id, $bookA->author->id, $bookA->translator?->id, $bookB->id, $bookB->author->id, $bookB->translator?->id];
			}
		}
		Assert::same([
			[1, 1, 1, 3, 2, 2],
			[1, 1, 1, 4, 2, 2],
			[2, 1, null, 3, 2, 2],
			[2, 1, null, 4, 2, 2],
		], $pairs);
	}


	public function testOneHasManyAndManyHasManyPreload(): void
	{
		$this->orm->clear();
		$result = [];
		$queries = $this->getQueries(function () use (&$result): void {
			foreach ($this->orm->authors->findAll()->orderBy('id') as $author) {
				$bookIds = [];
				foreach ($author->books as $book) {
					$tagIds = [];
					foreach ($book->tags->toCollection()->orderBy('id') as $tag) {
						$tagIds[] = $tag->id;
					}
					$bookIds[(int) $book->id] = $tagIds;
				}
				$result[(int) $author->id] = $bookIds;
			}
		});
		Assert::same([
			1 => [2 => [2, 3], 1 => [1, 2]],
			2 => [4 => [], 3 => [3]],
		], $result);
		if ($queries !== null) {
			Assert::true(count($queries) <= 6, implode("\n", $queries));
		}
	}
}


$test = new RelationshipsPreloadTest();
$test->run();

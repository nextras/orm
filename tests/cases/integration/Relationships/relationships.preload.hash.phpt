<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsPreloadHashTest extends DataTestCase
{
	public function testAlternatingFilteredOneHasManyQueries(): void
	{
		foreach ($this->orm->books->findAll()->orderBy('id') as $book) {
			$translator = $book->translator;
			if ($translator === null) continue;

			$first = $translator->translatedBooks->toCollection()->findBy(['title!=' => 'Book 1'])->orderBy('id');
			$second = $translator->translatedBooks->toCollection()->findBy(['title!=' => 'Book 3'])->orderBy('id');
			Assert::same($translator->id === 1 ? [] : [3, 4], $first->fetchPairs(null, 'id'));
			Assert::same($translator->id === 1 ? [1] : [4], $second->fetchPairs(null, 'id'));
			Assert::same($translator->id === 1 ? 0 : 2, $first->countStored());
			Assert::same(1, $second->countStored());
		}
	}


	public function testAlternatingFilteredManyHasManyQueries(): void
	{
		$expectedFirst = [1 => [2], 2 => [2, 3], 3 => [3], 4 => []];
		$expectedSecond = [1 => [1, 2], 2 => [2], 3 => [], 4 => []];
		foreach ($this->orm->books->findAll()->orderBy('id') as $book) {
			$id = $book->id;
			Assert::notNull($id);
			Assert::true(isset($expectedFirst[$id], $expectedSecond[$id]));
			$first = $book->tags->toCollection()->findBy(['name!=' => 'Tag 1'])->orderBy('id');
			$second = $book->tags->toCollection()->findBy(['name!=' => 'Tag 3'])->orderBy('id');
			Assert::same($expectedFirst[$id], $first->fetchPairs(null, 'id'));
			Assert::same($expectedSecond[$id], $second->fetchPairs(null, 'id'));
			Assert::same(count($expectedFirst[$id]), $first->countStored());
			Assert::same(count($expectedSecond[$id]), $second->countStored());
		}
	}
}


$test = new RelationshipsPreloadHashTest();
$test->run();

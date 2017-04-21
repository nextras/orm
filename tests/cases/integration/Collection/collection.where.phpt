<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionWhereTest extends DataTestCase
{
	public function testFindByAndOr()
	{
		$all = $this->orm->authors->findBy([
			ICollection::OR,
			'name' => 'Writer 1',
			'web' => 'http://example.com/2',
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->authors->findBy([
			ICollection::OR, // operator is irrelevant
			'name' => ['Writer 1', 'Writer 2'],
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			ICollection::AND,
			'author' => 1,
			'nextPart' => null,
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			ICollection::AND,
			'author' => 1,
			'nextPart' => null,
			'translator' => null,
		])->fetchAll();
		Assert::count(1, $all);


		$all = $this->orm->tags->findBy([
			ICollection::OR,
			[ICollection::AND, 'name' => 'Tag 1', 'isGlobal' => true], // match
			[ICollection::AND, 'name' => 'Tag 2', 'isGlobal' => false], // no-match
			[ICollection::AND, 'name' => 'Tag 3', 'isGlobal' => false], // match
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			ICollection::AND, // match 2
			[ICollection::OR, 'title' => 'Book 1', 'author' => 1], // match 1, 2
			[ICollection::OR, 'translator' => null, 'nextPart' => 3], // match 2, 4
		])->fetchAll();
		Assert::count(1, $all);
	}


	public function testFindByAndOrOldSyntax()
	{
		$all = $this->orm->books->findBy([
			'author' => 1,
			'nextPart' => null,
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			'author' => 1,
			'nextPart' => null,
			'translator' => null,
		])->fetchAll();
		Assert::count(1, $all);


		$all = $this->orm->tags->findBy([
			ICollection::OR,
			['name' => 'Tag 1', 'isGlobal' => true], // match
			['name' => 'Tag 2', 'isGlobal' => false], // no-match
			['name' => 'Tag 3', 'isGlobal' => false], // match
		])->fetchAll();
		Assert::count(2, $all);
	}
}


$test = new CollectionWhereTest($dic);
$test->run();

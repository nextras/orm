<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Model;


use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class ModelClearTest extends DataTestCase
{

	public function testBasics(): void
	{
		$book1 = $this->orm->books->getByIdChecked(1);
		$author1 = $book1->author;
		$author1Id = $author1->id;
		$book2 = $this->orm->books->getByIdChecked(1);

		Assert::equal($book1, $book2);

		$this->orm->clear();

		$book3 = $this->orm->books->getByIdChecked(1);
		Assert::notEqual($book1, $book3);
		$author3 = $book3->author;
		Assert::notEqual($author1, $author3);
		Assert::same($author1Id, $author3->id);
	}

	/*public function testMemoryGC(): void
	{
		$books = $this->orm->books->findAll();
		foreach ($books as $book) {
			$book->id;
		}

		$memory1 = memory_get_usage();

		unset($books, $book);
		$this->orm->clear();


		$books = $this->orm->books->findAll();
		foreach ($books as $book) {
			$book->id;
		}

		$memory2 = memory_get_usage();
		Assert::true($memory1 * 1.1 > $memory2);
	}*/

}


$test = new ModelClearTest();
$test->run();

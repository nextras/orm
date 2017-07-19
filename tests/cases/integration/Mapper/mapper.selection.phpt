<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MapperSelectionTest extends DataTestCase
{

	public function testToCollection()
	{
		$books = $this->orm->books->findBooksWithEvenId()->fetchPairs(NULL, 'id');
		Assert::same([2, 4], $books);
	}


	public function testToEntity()
	{
		$book = $this->orm->books->findFirstBook();
		Assert::type(Book::class, $book);
		Assert::same(1, $book->id);
	}

}


$test = new MapperSelectionTest($dic);
$test->run();

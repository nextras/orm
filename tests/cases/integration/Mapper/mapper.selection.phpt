<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;

use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MapperSelectionTest extends DataTestCase
{

	protected function setUp()
	{
		parent::setUp();
		if ($this->section === 'array') {
			Environment::skip('Test is only for Dbal mapper.');
		}
	}


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

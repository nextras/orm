<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class MapperSelectionTest extends DataTestCase
{

	protected function setUp()
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test is only for Dbal mapper.');
		}
	}


	public function testToCollection(): void
	{
		$books = $this->orm->books->findBooksWithEvenId()->fetchPairs(null, 'id');
		Assert::same([2, 4], $books);
	}


	public function testToEntity(): void
	{
		$book = $this->orm->books->findFirstBook();
		Assert::type(Book::class, $book);
		Assert::same(1, $book->id);
	}

}


$test = new MapperSelectionTest();
$test->run();

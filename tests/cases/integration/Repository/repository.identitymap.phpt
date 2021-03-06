<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryIdentityMapTest extends DataTestCase
{

	public function testPersistence(): void
	{
		$author = new Author();
		$author->name = 'A';

		$this->orm->authors->attach($author);

		$book = new Book();
		$book->title = 'B';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->authors->persistAndFlush($author);

		Assert::same($author->books->toCollection()->fetch(), $book);
	}

}


$test = new RepositoryIdentityMapTest();
$test->run();

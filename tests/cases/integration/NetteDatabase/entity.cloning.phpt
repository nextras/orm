<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityCloning2Test extends DataTestCase
{

	public function testCloningPersisted()
	{
		/** @var Book $book */
		$book = $this->orm->books->getById(1);

		$newBook = clone $book;

		Assert::same($book->author, $newBook->author);
		Assert::same(2, $newBook->tags->count());

		Assert::false($newBook->isPersisted());
		Assert::true($newBook->isModified());

		$this->orm->books->persistAndFlush($newBook);

		Assert::true($newBook->isPersisted());
		Assert::false($newBook->isModified());
		Assert::same(2, $newBook->tags->countStored());
	}

}


$test = new EntityCloning2Test($dic);
$test->run();

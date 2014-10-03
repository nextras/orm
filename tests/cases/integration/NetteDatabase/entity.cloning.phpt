<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityCloning2Test extends DatabaseTestCase
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

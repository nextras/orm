<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integrations;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityCloningTest extends TestCase
{

	public function testCloning()
	{
		/** @var Book $book */
		$author = $this->e('NextrasTests\Orm\Author');
		$book = $this->e('NextrasTests\Orm\Book', ['author' => $author]);
		$tag1 = $this->e('NextrasTests\Orm\Tag');
		$tag2 = $this->e('NextrasTests\Orm\Tag');
		$tag3 = $this->e('NextrasTests\Orm\Tag');

		$book->tags->set([$tag1, $tag2, $tag3]);
		$this->orm->books->persistAndFlush($book);

		$newBook = clone $book;

		Assert::same($book->author, $newBook->author);
		Assert::same(3, $newBook->tags->count());
		Assert::same([$tag1, $tag2, $tag3], iterator_to_array($newBook->tags));

		$book->author = $this->e('NextrasTests\Orm\Author');
		$book->tags->set([$tag1, $tag2]);

		Assert::same($author, $newBook->author);
		Assert::same([$tag1, $tag2, $tag3], iterator_to_array($newBook->tags));
	}

}


$test = new EntityCloningTest($dic);
$test->run();

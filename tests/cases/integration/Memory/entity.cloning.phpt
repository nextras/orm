<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityCloningTest extends TestCase
{

	public function testCloning()
	{
		/** @var Book $book */
		$author = $this->e('Nextras\Orm\Tests\Author');
		$book = $this->e('Nextras\Orm\Tests\Book', ['author' => $author]);
		$tag1 = $this->e('Nextras\Orm\Tests\Tag');
		$tag2 = $this->e('Nextras\Orm\Tests\Tag');
		$tag3 = $this->e('Nextras\Orm\Tests\Tag');

		$book->tags->set([$tag1, $tag2, $tag3]);
		$this->orm->books->persistAndFlush($book);

		$newBook = clone $book;

		Assert::same($book->author, $newBook->author);
		Assert::same(3, $newBook->tags->count());
		Assert::same([$tag1, $tag2, $tag3], iterator_to_array($newBook->tags));

		$book->author = $this->e('Nextras\Orm\Tests\Author');
		$book->tags->set([$tag1, $tag2]);

		Assert::same($author, $newBook->author);
		Assert::same([$tag1, $tag2, $tag3], iterator_to_array($newBook->tags));
	}

}


$test = new EntityCloningTest($dic);
$test->run();

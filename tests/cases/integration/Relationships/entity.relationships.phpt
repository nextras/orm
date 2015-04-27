<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\Tag;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityRelationshipsTest extends DataTestCase
{

	public function testBasics()
	{
		$author = new Author();
		$author->name = 'Jon Snow';

		$publisher = new Publisher();
		$publisher->name = '7K';

		$book = new Book();
		$book->title = 'A new book';
		$book->author = $author;
		$book->publisher = $publisher;
		$book->tags->set([new Tag('Awesome')]);

		$this->orm->books->persistAndFlush($book);

		Assert::true($author->isAttached());
		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);

		Assert::true($book->isAttached());
		Assert::true($book->isPersisted());
		Assert::false($book->isModified());
		Assert::same(5, $book->id);

		Assert::same(1, $book->tags->count());
		Assert::same(1, $book->tags->countStored());
		Assert::same('Awesome', $book->tags->get()->fetch()->name);
	}

}


$test = new EntityRelationshipsTest($dic);
$test->run();

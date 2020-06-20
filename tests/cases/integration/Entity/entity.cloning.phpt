<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Tag;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityCloning2Test extends DataTestCase
{

	public function testCloningOneHasMany(): void
	{
		/** @var Book $book */
		$book = $this->orm->books->getByIdChecked(1);

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


	public function testCloningManyHasMany(): void
	{
		$author = $this->e(Author::class);
		$book = $this->e(Book::class, ['author' => $author]);
		$tag1 = $this->e(Tag::class, ['name' => 'Tag 1']);
		$tag2 = $this->e(Tag::class, ['name' => 'Tag 2']);
		$tag3 = $this->e(Tag::class, ['name' => 'Tag 3']);

		$book->tags->set([$tag1, $tag2, $tag3]);
		$this->orm->books->persistAndFlush($book);

		$newBook = clone $book;

		Assert::same($book->author, $newBook->author);
		Assert::same(3, $newBook->tags->count());
		Assert::same([$tag1, $tag2, $tag3], iterator_to_array($newBook->tags));

		$book->author = $this->e(Author::class);
		$book->tags->set([$tag1, $tag2]);

		Assert::same($author, $newBook->author);
		Assert::same([$tag1, $tag2, $tag3], iterator_to_array($newBook->tags));
	}

}


$test = new EntityCloning2Test($dic);
$test->run();

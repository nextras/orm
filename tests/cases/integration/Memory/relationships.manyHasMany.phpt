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


class MemoryRelationshipManyHasManyTest extends TestCase
{

	public function testBasics()
	{
		$this->orm->books->persistAndFlush($this->e('NextrasTests\Orm\Book'));
		$this->orm->tags->persistAndFlush($this->e('NextrasTests\Orm\Tag', ['name' => 'Tag 1']));

		/** @var Book $book */
		$books = $this->orm->books->findAll();
		Assert::same(1, $books->count());
		Assert::same(1, $books->countStored());

		$book = $books->fetch();
		Assert::same(0, $book->tags->count());
		Assert::same(0, $book->tags->countStored());
		Assert::same(0, $book->tags->get()->count());
		Assert::same(0, $book->tags->get()->countStored());
		Assert::same([], $book->tags->get()->fetchPairs(NULL, 'name'));
	}


	public function testFetchMethods()
	{
		$book = $this->e('NextrasTests\Orm\Book');
		$book->tags->add($this->e('NextrasTests\Orm\Tag', ['name' => 'Tag 1']));
		$book->tags->add($this->e('NextrasTests\Orm\Tag', ['name' => 'Tag 2']));

		$this->orm->books->persist($book);
		$this->orm->flush();

		$tag = $book->tags->get()->findBy(['name' => 'Tag 2'])->fetch();
		Assert::same('Tag 2', $tag->name);
	}

}


$test = new MemoryRelationshipManyHasManyTest($dic);
$test->run();


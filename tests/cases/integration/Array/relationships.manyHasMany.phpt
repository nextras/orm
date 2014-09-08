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


class ArrayRelationshipManyHasManyTest extends TestCase
{

	public function testBasics()
	{
		$this->orm->books->persistAndFlush($this->e('Nextras\Orm\Tests\Book'));
		$this->orm->tags->persistAndFlush($this->e('Nextras\Orm\Tests\Tag', ['name' => 'Tag 1']));

		/** @var Book $book */
		$books = $this->orm->books->findAll();
		Assert::count(1, $books);

		$book = $books->fetch();
		Assert::count(0, $book->tags);
		Assert::count(0, $book->tags->get());
		Assert::same([], $book->tags->get()->fetchPairs(NULL, 'name'));
	}



	public function testFetchMethods()
	{
		$book = $this->e('Nextras\Orm\Tests\Book');
		$book->tags->add($this->e('Nextras\Orm\Tests\Tag', ['name' => 'Tag 1']));
		$book->tags->add($this->e('Nextras\Orm\Tests\Tag', ['name' => 'Tag 2']));

		$this->orm->books->persist($book);
		$this->orm->flush();

		$tag = $book->tags->get()->findBy(['name' => 'Tag 2'])->fetch();
		Assert::same('Tag 2', $tag->name);
	}

}


$test = new ArrayRelationshipManyHasManyTest($dic);
$test->run();


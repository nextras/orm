<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Entity\Collection\ICollection;
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
	}

}


$test = new ArrayRelationshipManyHasManyTest($dic);
$test->run();


<?php

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class RelationshipsHasOneIsChangedTest extends TestCase
{

	public function testBasic()
	{
		/** @var Author $author1 */
		/** @var Author $author2 */
		$author1 = $this->e('Nextras\Orm\Tests\Author');
		$author2 = $this->e('Nextras\Orm\Tests\Author');

		/** @var Book $book */
		$book = $this->e('Nextras\Orm\Tests\Book', ['author' => NULL]);

		Assert::null($book->author);

		$book->author = $author1;
		Assert::count(1, $author1->books);
		Assert::count(0, $author2->books);

		$book->author = $author2;
		Assert::count(0, $author1->books);
		Assert::count(1, $author2->books);

		$book->author = NULL;
		Assert::count(0, $author1->books);
		Assert::count(0, $author2->books);

		Assert::true($book->getProperty('author')->isModified());

		$book->author = NULL;
		Assert::true($book->getProperty('author')->isModified());
	}

}


$test = new RelationshipsHasOneIsChangedTest($dic);
$test->run();

<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsHasOneIsChangedTest extends TestCase
{

	public function testBasic()
	{
		/** @var Author $author1 */
		/** @var Author $author2 */
		$author1 = $this->e('Nextras\Orm\Tests\Author');
		$author2 = $this->e('Nextras\Orm\Tests\Author');

		/** @var Book $book */
		$book = $this->e('Nextras\Orm\Tests\Book');

		Assert::null($book->translator);

		$book->translator = $author1;
		Assert::same(1, $author1->translatedBooks->count());
		Assert::same(0, $author2->translatedBooks->count());

		$book->translator = $author2;
		Assert::same(0, $author1->translatedBooks->count());
		Assert::same(1, $author2->translatedBooks->count());

		$book->translator = NULL;
		Assert::same(0, $author1->translatedBooks->count());
		Assert::same(0, $author2->translatedBooks->count());

		Assert::true($book->getProperty('author')->isModified());

		$book->translator = NULL;
		Assert::true($book->getProperty('author')->isModified());
	}

}


$test = new RelationshipsHasOneIsChangedTest($dic);
$test->run();

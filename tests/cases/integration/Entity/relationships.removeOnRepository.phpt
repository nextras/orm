<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsRemoveOnRepositoryTest extends TestCase
{

	public function testBasic()
	{
		/** @var Author $author */
		$author = $this->e('Nextras\Orm\Tests\Author');
		$book1 = $this->e('Nextras\Orm\Tests\Book', ['author' => $author]);
		$book2 = $this->e('Nextras\Orm\Tests\Book', ['author' => $author]);

		Assert::same(2, $author->books->count());

		$this->orm->books->persistAndFlush($book1, FALSE);
		$this->orm->books->persistAndFlush($book2, FALSE);

		Assert::same(2, $author->books->count());

		$this->orm->books->removeAndFlush($book1);

		Assert::same(1, $author->books->count());
	}

}


$test = new RelationshipsRemoveOnRepositoryTest($dic);
$test->run();

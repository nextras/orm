<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasOneDRemoveTest extends DataTestCase
{

	public function testBasic()
	{
		/** @var Author $author */
		$author = $this->e('NextrasTests\Orm\Author');
		$book1 = $this->e('NextrasTests\Orm\Book', ['author' => $author]);
		$book2 = $this->e('NextrasTests\Orm\Book', ['author' => $author]);

		Assert::same(2, $author->books->count());

		$this->orm->books->persistAndFlush($book1, FALSE);
		$this->orm->books->persistAndFlush($book2, FALSE);

		Assert::same(2, $author->books->count());

		$this->orm->books->removeAndFlush($book1);

		Assert::same(1, $author->books->count());
	}

}


$test = new RelationshipsOneHasOneDRemoveTest($dic);
$test->run();

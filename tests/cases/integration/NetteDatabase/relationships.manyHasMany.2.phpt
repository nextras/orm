<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class RelationshipManyHasMany2Test extends DatabaseTestCase
{

	public function testEmptyPreloadContainer()
	{
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');
		$tags = [];

		foreach ($books as $book) {
			$book->setPreloadContainer(NULL);
			foreach ($book->tags->get()->orderBy('name') as $tag) {
				$tags[] = $tag->id;
			}
		}

		Assert::same([1, 2, 2, 3, 3], $tags);
	}

}


$test = new RelationshipManyHasMany2Test($dic);
$test->run();


<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasOneDirectedTest extends DatabaseTestCase
{

	public function testPersistance()
	{
		$book1 = new Book();
		$book1->author = $this->orm->authors->getById(1);
		$book1->title = 'Games of Thrones I';

		$book2 = new Book();
		$book2->author = $this->orm->authors->getById(1);
		$book2->title = 'Games of Thrones II';

		$book1->nextPart = $book2;
		$this->orm->books->persistAndFlush($book1);

		Assert::false($book1->isModified());
		Assert::same($book1->getRawValue('nextPart'), $book2->id);
	}

}


$test = new RelationshipOneHasOneDirectedTest($dic);
$test->run();

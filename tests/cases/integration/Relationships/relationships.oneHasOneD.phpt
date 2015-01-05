<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasOneDTest extends DataTestCase
{

	public function testPersistance()
	{
		$book1 = new Book();
		$book1->author = $this->orm->authors->getById(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getById(1);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 1;

		$book1->nextPart = $book2;
		$this->orm->books->persistAndFlush($book1);

		Assert::false($book1->isModified());
		Assert::same($book1->getRawValue('nextPart'), $book2->id);
	}

}


$test = new RelationshipOneHasOneDTest($dic);
$test->run();

<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
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


	public function testUpdateRelationship()
	{
		$book1 = new Book();
		$book1->author = $this->orm->authors->getById(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getById(1);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 1;

		$ean = new Ean();
		$ean->code = '1234';

		$ean->book = $book1;
		$ean->book = $book2;

		Assert::same($book2->ean, $ean);
		Assert::null($book1->ean);
	}


	public function testUpdateRelationshipWithNULL()
	{
		$book = new Book();
		$book->author = $this->orm->authors->getById(1);
		$book->title = 'Games of Thrones I';
		$book->publisher = 1;

		$ean1 = new Ean();
		$ean1->code = '1234';
		$ean1->book = $book;

		$ean2 = new Ean();
		$ean2->code = '1234';

		$book->getProperty('ean')->set($ean2, TRUE);

		// try it from other side

		$ean1->getProperty('book')->set($book, TRUE);

		Assert::same($ean1, $book->ean);
		Assert::false($ean2->hasValue('book'));
	}

}


$test = new RelationshipOneHasOneDTest($dic);
$test->run();

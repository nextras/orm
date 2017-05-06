<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasOneTest extends DataTestCase
{
	public function testCollection()
	{
		$book = new Book();
		$book->author = $this->orm->authors->getById(1);
		$book->title = 'GoT';
		$book->publisher = 1;

		$ean = new Ean();
		$ean->code = 'GoTEAN';
		$ean->book = $book;

		$this->orm->books->persistAndFlush($book);

		$eans = $this->orm->eans
			->findBy(['this->book->title' => 'GoT'])
			->orderBy('this->book->title');
		Assert::equal(1, $eans->countStored());
		Assert::equal(1, $eans->count());
		Assert::equal('GoTEAN', $eans->fetch()->code);
	}


	public function testPersistence()
	{
		$this->orm->clear();

		$book1 = new Book();
		$book1->author = $this->orm->authors->getById(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getById(2);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 2;

		$book1->nextPart = $book2;

		$this->orm->books->persistAndFlush($book1);

		Assert::false($book1->isModified());
		Assert::false($book2->isModified());
		Assert::same($book1->getRawValue('nextPart'), $book2->id);
		Assert::same($book2->getRawValue('previousPart'), $book1->id);
	}


	public function testPersistenceFromOtherSide()
	{
		$this->orm->clear();

		$book1 = new Book();
		$book1->author = $this->orm->authors->getById(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getById(2);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 2;

		$book1->nextPart = $book2;

		$this->orm->books->persistAndFlush($book2);

		Assert::false($book1->isModified());
		Assert::false($book2->isModified());
		Assert::same($book1->getRawValue('nextPart'), $book2->id);
		Assert::same($book2->getRawValue('previousPart'), $book1->id);
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

		$book->getProperty('ean')->set($ean2, true);

		// try it from other side

		$ean1->getProperty('book')->set($book, true);

		Assert::same($ean1, $book->ean);
		Assert::false($ean2->hasValue('book'));
	}


	public function testQueryBuilder()
	{
		$book = new Book();
		$book->author = $this->orm->authors->getById(1);
		$book->title = 'Games of Thrones I';
		$book->publisher = 1;

		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $book;

		$this->orm->books->persistAndFlush($book);

		$books = $this->orm->books->findBy(['this->ean->code' => '1234']);
		Assert::same(1, $books->countStored());
		Assert::same(1, $books->count());

		$eans = $this->orm->eans->findBy(['this->book->title' => 'Games of Thrones I']);
		Assert::same(1, $eans->countStored());
		Assert::same(1, $eans->count());
	}


	public function testRemove()
	{
		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $book = $this->orm->books->getById(1);

		$this->orm->eans->persistAndFlush($ean);
		$this->orm->eans->removeAndFlush($ean);

		Assert::false($ean->isPersisted());
		Assert::true($book->isPersisted());
	}


	public function testCascadeRemove()
	{
		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $book = $this->orm->books->getById(1);
		$this->orm->eans->persistAndFlush($ean);
		$eanId = $ean->id;

		$this->orm->clear();

		$ean = $this->orm->eans->getById($eanId);
		$ean->getMetadata()->getProperty('book')->isNullable = true;
		$ean->getMetadata()->getProperty('book')->relationship->cascade['remove'] = true;
		$this->orm->eans->removeAndFlush($ean);

		Assert::false($ean->isPersisted());
		Assert::null($this->orm->books->getById(1));
	}


	public function testCascadeRemoveWithNull()
	{
		$this->orm->eans->getEntityMetadata()->getProperty('book')->isNullable = true;
		$this->orm->books->getEntityMetadata()->getProperty('ean')->isNullable = false;

		$ean = new Ean();
		$ean->code = '1234';
		$this->orm->eans->persistAndFlush($ean);
		$this->orm->eans->removeAndFlush($ean);
		Assert::false($ean->isPersisted());
	}


	public function testGetRawValue()
	{
		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $this->orm->books->getById(1);
		$this->orm->eans->persistAndFlush($ean);
		$eanId = $ean->id;

		$this->orm->clear();

		$ean = $this->orm->eans->getById($eanId);
		$bookId = $ean->getRawValue('book');
		Assert::equal(1, $bookId);
	}
}


$test = new RelationshipOneHasOneTest($dic);
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\HasOne;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasOneTest extends DataTestCase
{
	public function testCollection(): void
	{
		$book = new Book();
		$book->author = $this->orm->authors->getByIdChecked(1);
		$book->title = 'GoT';
		$book->publisher = 1;

		$ean = new Ean();
		$ean->code = 'GoTEAN';
		$ean->book = $book;

		$this->orm->books->persistAndFlush($book);

		$eans = $this->orm->eans
			->findBy(['book->title' => 'GoT'])
			->orderBy('book->title');
		Assert::equal(1, $eans->countStored());
		Assert::equal(1, $eans->count());
		$fetched = $eans->fetch();
		Assert::notNull($fetched);
		Assert::equal('GoTEAN', $fetched->code);
	}


	public function testPersistence(): void
	{
		$this->orm->clear();

		$book1 = new Book();
		$book1->author = $this->orm->authors->getByIdChecked(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getByIdChecked(2);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 2;

		$book1->nextPart = $book2;

		$this->orm->books->persistAndFlush($book1);

		Assert::false($book1->isModified());
		Assert::false($book2->isModified());
		Assert::same($book1->getRawValue('nextPart'), $book2->id);
		Assert::same($book2->getRawValue('previousPart'), $book1->id);
	}


	public function testPersistenceFromOtherSide(): void
	{
		$this->orm->clear();

		$book1 = new Book();
		$book1->author = $this->orm->authors->getByIdChecked(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getByIdChecked(2);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 2;

		$book1->nextPart = $book2;

		$this->orm->books->persistAndFlush($book2);

		Assert::false($book1->isModified());
		Assert::false($book2->isModified());
		Assert::same($book1->getRawValue('nextPart'), $book2->id);
		Assert::same($book2->getRawValue('previousPart'), $book1->id);
	}


	public function testUpdateRelationship(): void
	{
		$book1 = new Book();
		$book1->author = $this->orm->authors->getByIdChecked(1);
		$book1->title = 'Games of Thrones I';
		$book1->publisher = 1;

		$book2 = new Book();
		$book2->author = $this->orm->authors->getByIdChecked(1);
		$book2->title = 'Games of Thrones II';
		$book2->publisher = 1;

		$ean = new Ean();
		$ean->code = '1234';

		$ean->book = $book1;
		$ean->book = $book2;

		Assert::same($book2->ean, $ean);
		Assert::null($book1->ean);
	}


	public function testUpdateRelationshipWithNULL(): void
	{
		$book = new Book();
		$book->author = $this->orm->authors->getByIdChecked(1);
		$book->title = 'Games of Thrones I';
		$book->publisher = 1;

		$ean1 = new Ean();
		$ean1->code = '1234';
		$ean1->book = $book;

		$ean2 = new Ean();
		$ean2->code = '1234';

		$property = $book->getProperty('ean');
		\assert($property instanceof HasOne);
		$property->set($ean2, true);

		// try it from other side

		$property = $ean1->getProperty('book');
		\assert($property instanceof HasOne);
		$property->set($book, true);

		Assert::same($ean1, $book->ean);
		Assert::false($ean2->hasValue('book'));
	}


	public function testQueryBuilder(): void
	{
		$book = new Book();
		$book->author = $this->orm->authors->getByIdChecked(1);
		$book->title = 'Games of Thrones I';
		$book->publisher = 1;

		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $book;

		$this->orm->books->persistAndFlush($book);

		$books = $this->orm->books->findBy(['ean->code' => '1234']);
		Assert::same(1, $books->countStored());
		Assert::same(1, $books->count());

		$eans = $this->orm->eans->findBy(['book->title' => 'Games of Thrones I']);
		Assert::same(1, $eans->countStored());
		Assert::same(1, $eans->count());
	}


	public function testRemove(): void
	{
		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $book = $this->orm->books->getByIdChecked(1);

		$this->orm->eans->persistAndFlush($ean);
		$this->orm->eans->removeAndFlush($ean);

		Assert::false($ean->isPersisted());
		Assert::true($book->isPersisted());
	}


	public function testCascadeRemove(): void
	{
		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $book = $this->orm->books->getByIdChecked(1);
		$this->orm->eans->persistAndFlush($ean);
		$eanId = $ean->id;

		$this->orm->clear();

		$ean = $this->orm->eans->getByIdChecked($eanId);
		$metadata = $ean->getMetadata()->getProperty('book');
		$metadata->isNullable = true;
		Assert::notNull($metadata->relationship);
		$metadata->relationship->cascade['remove'] = true;
		$this->orm->eans->removeAndFlush($ean);

		Assert::false($ean->isPersisted());
		Assert::null($this->orm->books->getById(1));
	}


	public function testCascadeRemoveWithNull(): void
	{
		$this->orm->eans->getEntityMetadata()->getProperty('book')->isNullable = true;
		$this->orm->books->getEntityMetadata()->getProperty('ean')->isNullable = false;

		$ean = new Ean();
		$ean->code = '1234';
		$this->orm->eans->persistAndFlush($ean);
		$this->orm->eans->removeAndFlush($ean);
		Assert::false($ean->isPersisted());
	}


	public function testGetRawValue(): void
	{
		$ean = new Ean();
		$ean->code = '1234';
		$ean->book = $this->orm->books->getByIdChecked(1);
		$this->orm->eans->persistAndFlush($ean);
		$eanId = $ean->id;

		$this->orm->clear();

		$ean = $this->orm->eans->getByIdChecked($eanId);
		$bookId = $ean->getRawValue('book');
		Assert::equal(1, $bookId);
	}
}


$test = new RelationshipOneHasOneTest();
$test->run();

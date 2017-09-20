<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Model;

use Nextras\Dbal\IConnection;
use Nextras\Orm\InvalidStateException;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class ModelRefreshAllTest extends DataTestCase
{

	public function setUp()
	{
		parent::setUp();
		if ($this->section === 'array') {
			Environment::skip('Test is only for Dbal mapper.');
		}
	}


	public function testBasics()
	{
		$book1 = $this->orm->books->getById(1);
		Assert::same('Book 1', $book1->title);
		$book2 = $this->orm->books->getById(2);
		Assert::same('Book 2', $book2->title);
		$this->container->getByType(IConnection::class)->query('UPDATE %table SET %set WHERE id = %i', 'books', ['title' => 'foo'], 1);
		$this->container->getByType(IConnection::class)->query('UPDATE %table SET %set WHERE id = %i', 'books', ['title' => 'bar'], 2);

		Assert::same('Book 1', $book1->title);
		Assert::same('Book 2', $book2->title);

		$this->orm->refreshAll();
		Assert::same('foo', $book1->title);
		Assert::same('bar', $book2->title);

		Assert::false($book1->isModified());
		Assert::false($book2->isModified());
	}


	public function testMHMRelations()
	{
		$book2 = $this->orm->books->getById(2);
		Assert::count(2, iterator_to_array($book2->tags));

		$this->container->getByType(IConnection::class)->query('DELETE FROM %table WHERE book_id = %i AND tag_id = %i', 'books_x_tags', 2, 3);

		Assert::count(2, iterator_to_array($book2->tags));
		$this->orm->refreshAll();
		Assert::count(1, iterator_to_array($book2->tags));
		Assert::false($book2->isModified());
	}


	public function testMHMRelations2()
	{
		$tag3 = $this->orm->tags->getById(3);

		Assert::count(2, iterator_to_array($tag3->books));
		$this->container->getByType(IConnection::class)->query('DELETE FROM %table WHERE book_id = %i AND tag_id = %i', 'books_x_tags', 2, 3);

		Assert::count(2, iterator_to_array($tag3->books));
		$this->orm->refreshAll();
		Assert::count(1, iterator_to_array($tag3->books));
		Assert::false($tag3->isModified());
	}


	public function testOHMRelations()
	{
		$book1 = $this->orm->books->getById(1);
		$publisher1 = $this->orm->publishers->getById(1);
		$publisher2 = $this->orm->publishers->getById(2);
		Assert::same($publisher1, $book1->publisher);
		Assert::count(2, $publisher1->books);
		Assert::count(1, $publisher2->books);

		$this->container->getByType(IConnection::class)->query('UPDATE %table SET %set WHERE id = %i', 'books', ['publisher_id' => 2], 1);

		Assert::same($publisher1, $book1->publisher);
		Assert::count(2, $publisher1->books);
		Assert::count(1, $publisher2->books);

		$this->orm->refreshAll();

		Assert::same($publisher2, $book1->publisher);
		Assert::count(1, $publisher1->books);
		Assert::count(2, $publisher2->books);
		Assert::false($book1->isModified());
		Assert::false($publisher1->isModified());
		Assert::false($publisher2->isModified());
	}


	public function testOHORelations()
	{
		$connection = $this->container->getByType(IConnection::class);
		if ($this->section === Helper::SECTION_MSSQL) {
			$connection->query('SET IDENTITY_INSERT eans ON;');
		}

		$connection->query('INSERT INTO %table %values', 'eans', ['id' => 1, 'code' => '111']);
		$connection->query('UPDATE %table SET %set WHERE id = %i', 'books', ['ean_id' => 1], 1);

		$book1 = $this->orm->books->getById(1);
		$ean1 = $this->orm->eans->getById(1);

		Assert::same($ean1, $book1->ean);

		$connection->query('INSERT INTO %table %values', 'eans', ['id' => 2, 'code' => '222']);
		$connection->query('UPDATE %table SET %set WHERE id = %i', 'books', ['ean_id' => 2], 1);
		$connection->query('DELETE FROM %table WHERE id = %i', 'eans', 1);

		Assert::same($ean1, $book1->ean);

		$this->orm->refreshAll();

		Assert::notSame($ean1, $book1->ean);

		$ean2 = $this->orm->eans->getById(2);
		Assert::same($ean2, $book1->ean);

		Assert::false($book1->isModified());
		Assert::false($ean1->isModified());

		Assert::false($ean1->isPersisted());
	}


	public function testDelete()
	{
		$book1 = $this->orm->books->getById(1);
		Assert::same(1, $book1->getPersistedId());
		$this->container->getByType(IConnection::class)->query('DELETE FROM %table WHERE id = %i', 'books', 1);
		Assert::same(1, $book1->getPersistedId());

		$this->orm->refreshAll();
		Assert::null($book1->getPersistedId());
		Assert::false($book1->isModified());
	}


	public function testDisallowOverwrite()
	{
		$book1 = $this->orm->books->getById(1);
		$book1->title = 'foo';
		Assert::exception(function () {
			$this->orm->refreshAll();
		}, InvalidStateException::class);
		Assert::same('foo', $book1->title);
		Assert::true($book1->isModified());
	}


	public function testAllowOverwrite()
	{
		$book1 = $this->orm->books->getById(1);
		$book1->title = 'foo';
		$this->orm->refreshAll(TRUE);
		Assert::same('Book 1', $book1->title);
		Assert::false($book1->isModified());
	}


	public function testTrackedMHM()
	{
		$tag3 = $this->orm->tags->getById(3);
		$book2 = $this->orm->books->getById(2);

		Assert::false($book2->isModified());
		$book2->title = 'abc';
		Assert::true($book2->isModified());

		$this->orm->persist($tag3);
		//relation not loaded yet
		Assert::true($book2->isModified());

		iterator_to_array($tag3->books);
		$this->orm->persist($tag3);
		Assert::false($book2->isModified());

		$this->orm->flush();
		$this->orm->refreshAll();

		$book2->title = 'xyz';
		Assert::true($book2->isModified());

		$this->orm->persist($tag3);
		//relation was invalidated by refreshAll
		Assert::true($book2->isModified());

		iterator_to_array($tag3->books);
		$this->orm->persist($tag3);
		Assert::false($book2->isModified());
	}


	public function testTrackedOHM()
	{
		$book1 = $this->orm->books->getById(1);
		$publisher1 = $this->orm->publishers->getById(1);

		Assert::false($book1->isModified());
		$book1->title = 'abc';
		Assert::true($book1->isModified());

		$this->orm->persist($publisher1);
		//relation not loaded yet
		Assert::true($book1->isModified());

		iterator_to_array($publisher1->books);
		$this->orm->persist($publisher1);
		Assert::false($book1->isModified());

		$this->orm->flush();
		$this->orm->refreshAll();

		Assert::false($book1->isModified());
		$book1->title = 'xyz';
		Assert::true($book1->isModified());

		$this->orm->persist($publisher1);
		//relation was invalidated by refreshAll
		Assert::true($book1->isModified());

		iterator_to_array($publisher1->books);
		$this->orm->persist($publisher1);
		Assert::false($book1->isModified());
	}

}


$test = new ModelRefreshAllTest($dic);
$test->run();

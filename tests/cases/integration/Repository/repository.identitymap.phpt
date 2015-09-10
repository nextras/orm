<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use Nextras\Orm\Model\IModel;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryIdentityMapTest extends DataTestCase
{

	public function testPersistance()
	{
		$author = new Author();
		$author->name = 'A';

		$this->orm->authors->attach($author);

		$book = new Book();
		$book->title = 'B';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->authors->persistAndFlush($author);

		Assert::same($author->books->get()->fetch(), $book);
	}

	public function testCreateEntity()
	{
		$bookA = new Book();
		$bookA->title = 'B';
		$bookA->author = new Author();
		$bookA->author->name = 'A';
		$bookA->publisher = new Publisher();
		$bookA->publisher->name = 'P';

		$bookA->createdAt = new \DateTime('2015-01-01 12:01+02:00');
		$bookA->createdAt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		Assert::same('2015-01-01T11:01:00+01:00', $bookA->createdAt->format('c'));
		$this->orm->books->persistAndFlush($bookA);
		$id = $bookA->getPersistedId();
		$exp = $bookA->createdAt->format('c');

		$this->orm->clearIdentityMapAndCaches(IModel::I_KNOW_WHAT_I_AM_DOING);

		$bookB = $this->orm->books->getById($id);
		Assert::same($exp, $bookB->createdAt->format('c'));
	}

}


$test = new RepositoryIdentityMapTest($dic);
$test->run();

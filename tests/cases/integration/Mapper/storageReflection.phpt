<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;

use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class StorageReflectionTest extends DataTestCase
{
	public function testTimezoneDetection()
	{
		date_default_timezone_set('Europe/Prague'); // mut be different from UTC

		$bookA = new Book();
		$bookA->title = 'B';
		$bookA->author = new Author();
		$bookA->author->name = 'A';
		$bookA->publisher = new Publisher();
		$bookA->publisher->name = 'P';
		$bookA->publishedAt = '2015-09-09 10:10:10';

		Assert::same('2015-09-09T10:10:10+02:00', $bookA->publishedAt->format('c'));

		$this->orm->books->persistAndFlush($bookA);
		$id = $bookA->getPersistedId();
		$this->orm->clear();

		$bookB = $this->orm->books->getById($id);
		Assert::same('2015-09-09T10:10:10+02:00', $bookB->publishedAt->format('c'));
	}
}


$test = new StorageReflectionTest($dic);
$test->run();

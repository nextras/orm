<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use Nextras\Dbal\Utils\DateTimeImmutable;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class ConventionsTest extends DataTestCase
{
	public function testTimezoneDetection(): void
	{
		date_default_timezone_set('Europe/Prague'); // mut be different from UTC

		$bookA = new Book();
		$bookA->title = 'B';
		$bookA->author = new Author();
		$bookA->author->name = 'A';
		$bookA->publisher = new Publisher();
		$bookA->publisher->name = 'P';
		$bookA->publishedAt = new DateTimeImmutable('2015-09-09 10:10:10');

		Assert::same('2015-09-09T10:10:10+02:00', $bookA->publishedAt->format('c'));

		$this->orm->books->persistAndFlush($bookA);
		$id = $bookA->getPersistedId();
		$this->orm->clear();

		$bookB = $this->orm->books->getByIdChecked($id);
		Assert::same('2015-09-09T10:10:10+02:00', $bookB->publishedAt->format('c'));
	}
}


$test = new ConventionsTest();
$test->run();

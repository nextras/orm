<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use DateTime;
use DateTimeImmutable;
use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Orm\InvalidStateException;
use NextrasTests\Orm\BookCollection;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class DbalPersistAutoupdateMapperTest extends DataTestCase
{
	public function setUp()
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY || $this->section === Helper::SECTION_MSSQL) {
			Environment::skip('Test is only for Dbal mapper.');
		}
	}


	public function testInsertAndUpdate(): void
	{
		$bookCollection = new BookCollection();
		$bookCollection->name = 'Test Collection 1';

		Assert::null($bookCollection->updatedAt);
		$this->orm->bookColletions->persistAndFlush($bookCollection);

		Assert::type(DateTimeImmutable::class, $bookCollection->updatedAt);
		$old = $bookCollection->updatedAt;

		sleep(1);
		$bookCollection->name .= '1';
		$this->orm->bookColletions->persistAndFlush($bookCollection);

		Assert::same('Test Collection 11', $bookCollection->name);
		Assert::type(DateTimeImmutable::class, $bookCollection->updatedAt);
		$new = $bookCollection->updatedAt;
		Assert::notEqual($old->format(DateTime::ISO8601), $new->format(DateTime::ISO8601));

		/** @var IConnection $connection */
		$connection = $this->container->getByType(Connection::class);
		$connection->query('DELETE FROM book_collections WHERE id = %i', $bookCollection->id);

		$bookCollection->name .= '2';
		Assert::exception(function () use ($bookCollection) {
			$this->orm->bookColletions->persistAndFlush($bookCollection);
		}, InvalidStateException::class, 'Refetching data failed. Entity is not present in storage anymore.');
	}
}


$test = new DbalPersistAutoupdateMapperTest($dic);
$test->run();

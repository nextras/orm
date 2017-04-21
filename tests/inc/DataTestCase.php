<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Utils\FileImporter;
use Nextras\Orm\NotSupportedException;


class DataTestCase extends TestCase
{
	protected function setUp()
	{
		parent::setUp();
		switch ($this->section) {
			case Helper::SECTION_MYSQL:
			case Helper::SECTION_PGSQL:
				$connection = $this->container->getByType(Connection::class);
				FileImporter::executeFile($connection, __DIR__ . "/../db/$this->section-data.sql");
				break;

			case Helper::SECTION_ARRAY:
				$orm = $this->orm;
				require __DIR__ . "/../db/array-data.php";
				break;

			default:
				throw new NotSupportedException();
		}
	}


	protected function getQueries(callable $callback)
	{
		$conn = $this->container->getByType(Connection::class, FALSE);

		if (!$conn) {
			$callback();
			return [];
		}

		$queries = [];
		$conn->onQuery[__CLASS__] = function ($conn, $sql) use (& $queries) {
			if (preg_match('#(pg_catalog|information_schema|SHOW\s+FULL|SELECT\s+CURRVAL)#i', $sql) === 1) {
				return;
			}

			$queries[] = $sql;
			echo $sql, "\n";
		};

		try {
			$callback();
			return $queries;

		} finally {
			unset($conn->onQuery[__CLASS__]);
		}
	}
}

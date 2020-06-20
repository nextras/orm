<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Dbal\IConnection;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\CallbackQueryLogger;
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
			case Helper::SECTION_MSSQL:
				$connection = $this->container->getByType(IConnection::class);
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


	/**
	 * @param callable():void $callback
	 * @return array<string>|null
	 */
	protected function getQueries(callable $callback): ?array
	{
		$conn = $this->container->getByType(IConnection::class, false);

		if (!$conn) {
			$callback();
			return null;
		}

		$queries = [];
		$queryLogger = new CallbackQueryLogger(
			function (string $sqlQuery) use (&$queries) : void {
				if (preg_match('#(pg_catalog|information_schema|SHOW\s+FULL|SELECT\s+CURRVAL|@@IDENTITY|SCOPE_IDENTITY)#i', $sqlQuery) === 1) {
					return;
				}

				$queries[] = $sqlQuery;
				echo $sqlQuery, "\n";
			}
		);

		$conn->addLogger($queryLogger);

		try {
			$callback();
			return $queries;

		} finally {
			$conn->removeLogger($queryLogger);
		}
	}
}

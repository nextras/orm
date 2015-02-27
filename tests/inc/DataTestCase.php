<?php

namespace NextrasTests\Orm;

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
				$connection = $this->container->getByType('Nextras\Dbal\Connection');
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

}

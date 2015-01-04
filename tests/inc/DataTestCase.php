<?php

namespace NextrasTests\Orm;

use Nette\Database\Helpers;
use Nextras\Orm\NotSupportedException;
use Tester\Environment;


class DataTestCase extends TestCase
{

	protected function setUp()
	{
		parent::setUp();
		switch ($this->section) {
			case Helper::SECTION_MYSQL:
			case Helper::SECTION_PGSQL:
				$connection = $this->container->getByType('Nette\Database\Connection');
				Environment::lock("integration-$this->section", TEMP_DIR);
				Helpers::loadFromFile($connection, __DIR__ . "/../db/$this->section-data.sql");
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

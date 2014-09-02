<?php

namespace Nextras\Orm\Tests;

use Nette\Database\Helpers;
use Nette\DI\Container;


class DatabaseTestCase extends TestCase
{

	public function __construct(Container $dic)
	{
		parent::__construct($dic);
		$connection = $dic->getByType('Nette\Database\Connection');
		$database = substr($dic->parameters['database_dsn'], 0, 5);
		Environment::lock('integration-' . $database, TEMP_DIR);
		Helpers::loadFromFile($connection, __DIR__ . "/../db/{$database}-data.sql");
		$this->orm = $dic->getService('orm.model');
	}

}

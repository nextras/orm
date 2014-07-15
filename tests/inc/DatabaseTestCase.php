<?php

namespace Nextras\Orm\Tests;

use Nette\Database\Helpers;
use Nette\DI\Container;


class DatabaseTestCase extends TestCase
{
	/** @var Model */
	protected $orm;


	public function __construct(Container $dic)
	{
		parent::__construct($dic);
		$connection = $dic->getByType('Nette\Database\Connection');
		Helpers::loadFromFile($connection, __DIR__ . '/../db/mysql-data.sql');
		$this->orm = $dic->getService('orm.model');
	}

}

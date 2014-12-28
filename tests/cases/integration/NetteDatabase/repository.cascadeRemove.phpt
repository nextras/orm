<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use NextrasTests\Orm\DatabaseTestCase;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepostiroyCascadeRemoveTest extends DatabaseTestCase
{

	public function testBasicCascadeRemove()
	{
		Environment::$checkAssertions = FALSE;
		$this->orm->authors->removeAndFlush(1, TRUE);
	}

}


$test = new RepostiroyCascadeRemoveTest($dic);
$test->run();

<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepostiroyCascadeRemoveTest extends DatabaseTestCase
{

	public function testBasicCascadeRemove()
	{
		Environment::$checkAssertions = FALSE;
		$this->orm->authors->removeAndFlush(1);
	}

}


$test = new RepostiroyCascadeRemoveTest($dic);
$test->run();

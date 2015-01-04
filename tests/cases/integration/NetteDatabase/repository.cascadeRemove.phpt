<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\NetteDatabase;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepostiroyCascadeRemoveTest extends DataTestCase
{

	public function testBasicCascadeRemove()
	{
		Environment::$checkAssertions = FALSE;
		$this->orm->authors->removeAndFlush(1, TRUE);
	}

}


$test = new RepostiroyCascadeRemoveTest($dic);
$test->run();

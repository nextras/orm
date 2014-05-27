<?php

namespace Nextras\Orm\Tests;

use Mockery;
use Tester;


class TestCase extends Tester\TestCase
{

	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}

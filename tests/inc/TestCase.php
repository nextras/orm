<?php

namespace Nextras\Orm\Tests;

use Mockery;
use Nette\DI\Container;
use Nextras\Orm\TestHelper\TestCaseEntityTrait;
use Tester;


class TestCase extends Tester\TestCase
{
	use TestCaseEntityTrait;


	/** @var Container */
	protected $container;


	public function __construct(Container $dic)
	{
		$this->container = $dic;
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}

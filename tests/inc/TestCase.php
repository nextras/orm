<?php

namespace NextrasTests\Orm;

use Mockery;
use Nette\DI\Container;
use Nextras\Orm\TestHelper\TestCaseEntityTrait;
use Tester;


class TestCase extends Tester\TestCase
{
	use TestCaseEntityTrait;


	/** @var Container */
	protected $container;

	/** @var Model */
	protected $orm;


	public function __construct(Container $dic)
	{
		$this->container = $dic;
		$this->orm = $dic->getService('testOrm.model');
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}

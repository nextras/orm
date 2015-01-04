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

	/** @var string */
	protected $section;


	public function __construct(Container $container)
	{
		$this->container = $container;
	}


	protected function setUp()
	{
		parent::setUp();
		$this->orm = $this->container->getByType('Nextras\Orm\Model\IModel');
		$this->section = Helper::getSection();

		if ($this->section === Helper::SECTION_ARRAY) {
			$orm = $this->orm;
			require __DIR__ . "/../db/array-init.php";
		}
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}

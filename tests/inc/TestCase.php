<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Mockery;
use Nette\DI\Container;
use Nextras\Orm\Model\IModel;
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
		$this->orm = $this->container->getByType(IModel::class);
		$this->section = Helper::getSection();

		if ($this->section === Helper::SECTION_ARRAY) {
			$orm = $this->orm;
			require __DIR__ . "/../db/array-init.php";
		} else {
			Tester\Environment::lock("integration-{$this->section}", TEMP_DIR);
		}
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}

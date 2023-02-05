<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Mockery;
use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nextras\Dbal\IConnection;
use Nextras\Orm\TestHelper\TestCaseEntityTrait;
use Tester;
use Tester\Environment;
use function assert;
use function json_encode;
use function md5;


class TestCase extends Tester\TestCase
{
	use TestCaseEntityTrait;


	/** @var Container */
	protected $container;

	/** @var Model */
	protected $orm;

	/** @var string|null */
	protected $section;

	/** @var QueryChecker|null */
	private $queryChecker;

	/** @var string */
	private $testId;


	protected function setUpData(): void
	{
	}


	protected function setUp()
	{
		$annotations = Environment::getTestAnnotations();
		$dbConfig = null;

		if (isset($annotations['dataprovider'])) {
			$dbConfig = Environment::loadData();
			$this->section = Helper::getSection($dbConfig);
		} else {
			$this->section = Helper::SECTION_ARRAY;
		}

		$configurator = new Configurator();

		if (!Helper::isRunByRunner()) {
			$configurator->enableTracy(__DIR__ . '/../log');
		}

		$hashData = json_encode($dbConfig);
		$hash = md5($hashData !== false ? $hashData : '');

		if ($this->section !== Helper::SECTION_ARRAY) {
			assert($dbConfig !== null);
			$configurator->addStaticParameters([
				'container' => ['class' => "Dbal{$hash}SystemContainer"],
				'db' => $dbConfig + ['port' => null],
				'autowired1' => $this->section !== Helper::SECTION_MSSQL,
				'autowired2' => $this->section === Helper::SECTION_MSSQL,
			]);
			$configurator->addConfig(__DIR__ . '/../config.dbal.neon');
		} else {
			$configurator->addStaticParameters(['container' => ['class' => 'ArraySystemContainer']]);
			$configurator->addConfig(__DIR__ . '/../config.array.neon');
		}

		$configurator->setTempDirectory(TEMP_DIR);
		$this->container = $configurator->createContainer();
		$this->orm = $this->container->getByType(Model::class);

		if ($this->section === Helper::SECTION_ARRAY) {
			$orm = $this->orm;
			require __DIR__ . "/../db/array-init.php";
		} elseif ($this->section !== null) {
			Tester\Environment::lock("integration-$hash", TEMP_DIR);
		}

		$this->setUpData();

		if ($this->section === Helper::SECTION_PGSQL) {
			$this->queryChecker = new QueryChecker($this->testId);
			$connection = $this->container->getByType(IConnection::class);
			$connection->addLogger($this->queryChecker);
		}
	}


	/**
	 * @param array<mixed>|null $args
	 */
	public function runTest(string $method, array $args = null): void
	{
		$this->testId = get_class($this) . '_' . $method;
		parent::runTest($method, $args);
	}


	protected function tearDown()
	{
		parent::tearDown();
		if ($this->queryChecker !== null) {
			$this->queryChecker->assert();
		}
		Mockery::close();
	}
}

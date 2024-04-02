<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Entity\Entity;use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\Repository;
use NextrasTests\Orm\BooksRepository;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';

function buildDic(string $config): Container
{
	$cacheDir = TEMP_DIR . '/cache/bridge-nette-dic-extension-multiple';
	$loader = new ContainerLoader($cacheDir);
	$key = __FILE__ . ':' . __LINE__ . ':' . $config;
	$className = $loader->load(function (Compiler $compiler) use ($config, $cacheDir): void {
		$compiler->addExtension('extensions', new ExtensionsExtension());
		$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
		$compiler->loadConfig($config);
	}, $key);

	/** @var Container $dic */
	$dic = new $className();
	return $dic;
}

/**
 * @property int $id {primary}
 */
class TestEntity extends Entity
{
}

/**
 * @extends Repository<TestEntity>
 */
class TestRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [TestEntity::class];
	}

}

/**
 * @extends DbalMapper<TestEntity>
 */
class TestMapper extends DbalMapper
{
}

/**
 * @property-read TestRepository $test
 */
class Model2 extends Model
{
}

$container = buildDic(__DIR__ . '/dic-extension-multiple.neon');
Assert::count(2, $container->findByType(IModel::class));
Assert::count(2, $container->findByType(DbalMapperCoordinator::class));
// check that returns only one instance
Assert::type(DbalMapperCoordinator::class, $container->getByType(DbalMapperCoordinator::class));
Assert::type(BooksRepository::class, $container->getByType(BooksRepository::class));
Assert::type(TestRepository::class, $container->getByType(TestRepository::class));

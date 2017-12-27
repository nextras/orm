<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Integration\BridgeNetteDI;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Model\IModel;
use NextrasTests\Orm\ContentsRepository;
use NextrasTests\Orm\Thread;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';

function buildDic($config)
{
	$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-finder';
	$loader = new ContainerLoader($cacheDir);
	$key = __FILE__ . ':' . __LINE__ . ':' . $config;
	$className = $loader->load(function (Compiler $compiler) use ($config, $cacheDir) {
		$compiler->addExtension('extensions', new ExtensionsExtension());
		$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
		$compiler->loadConfig($config);
	}, $key);

	/** @var Container $dic */
	$dic = new $className;
	return $dic;
}

$container = buildDic(__DIR__ . '/dic-finder.neon');
assert($container instanceof Container);
$model = $container->getByType(IModel::class);
assert($model instanceof IModel);

$thread = new Thread();

$contentsRepository = $model->getRepository(ContentsRepository::class);
$contentsRepository->persistAndFlush($thread);

Assert::same(1, $contentsRepository->findAll()->countStored());

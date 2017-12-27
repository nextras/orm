<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Integration\BridgeNetteDI;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Model\IModel;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';

function buildDic($config)
{
	$cacheDir = TEMP_DIR . '/cache/bridge-nette-dic-extension-order';
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

$container = buildDic(__DIR__ . '/dic-extension-order.neon');
assert($container instanceof Container);

Assert::true($container->findByType(IModel::class) != null);

<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Async\CollectionFetcher;
use Nextras\Orm\Async\IQueryExecutor;
use Nextras\Orm\Async\NativeAsyncQueryExecutor;
use Nextras\Orm\Async\SequentialQueryExecutor;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

function buildDic(string $config): Container
{
	$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-async';
	$loader = new ContainerLoader($cacheDir);
	$key = __FILE__ . ':' . __LINE__ . ':' . $config;
	$className = $loader->load(function (Compiler $compiler) use ($config, $cacheDir): string|null {
		$compiler->addExtension('extensions', new ExtensionsExtension());
		$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
		$compiler->loadConfig($config);
		return null;
	}, $key);

	$dic = new $className();
	assert($dic instanceof Container);
	return $dic;
}

$container = buildDic(__DIR__ . '/dic-async.neon');
Assert::type(NativeAsyncQueryExecutor::class, $container->getByType(IQueryExecutor::class));
Assert::type(CollectionFetcher::class, $container->getByType(CollectionFetcher::class));

$container = buildDic(__DIR__ . '/dic-finder.neon');
Assert::type(SequentialQueryExecutor::class, $container->getByType(IQueryExecutor::class));
Assert::type(CollectionFetcher::class, $container->getByType(CollectionFetcher::class));

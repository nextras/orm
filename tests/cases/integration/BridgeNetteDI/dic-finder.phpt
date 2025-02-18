<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Model\IModel;
use NextrasTests\Orm\TimeSeries;
use NextrasTests\Orm\TimeSeriesRepository;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';

function buildDic(string $config): Container
{
	$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-finder';
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

$container = buildDic(__DIR__ . '/dic-finder.neon');
$model = $container->getByType(IModel::class);

$timeSeries = new TimeSeries();
$timeSeries->date = 'now';
$timeSeries->value = 1;

$timeSeriesRepository = $model->getRepository(TimeSeriesRepository::class);
$timeSeriesRepository->persistAndFlush($timeSeries);

Assert::same(1, $timeSeriesRepository->findAll()->countStored());

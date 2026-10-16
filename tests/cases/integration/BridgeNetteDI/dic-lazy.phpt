<?php declare(strict_types = 1);

/**
 * @phpVersion >= 8.4
 * @dataProvider dic-lazy.ini
 */

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\DIExtension;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use NextrasTests\Orm\Author;
use ReflectionClass;
use ReflectionProperty;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


$args = Environment::loadData();
$mode = $args['initializeMetadata'];

$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-lazy-' . $mode;
$configure = function (Compiler $compiler) use ($cacheDir, $mode): ?string {
	$compiler->addExtension('extensions', new ExtensionsExtension());
	$compiler->addExtension('di', new DIExtension());
	$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
	$compiler->loadConfig(__DIR__ . '/dic-lazy.neon');
	if ($mode === 'enabled' || $mode === 'disabled') {
		$compiler->addConfig(['nextras.orm' => ['initializeMetadata' => $mode === 'enabled']]);
	} elseif ($mode === 'workaround') {
		// the pre-6.0 workaround: eager initialization and non-lazy metadata storage service
		$compiler->addConfig([
			'nextras.orm' => ['initializeMetadata' => true],
			'services' => ['nextras.orm.metadataStorage' => ['lazy' => false]],
		]);
	}
	return null;
};

$compile = function () use ($configure): void {
	$compiler = new Compiler();
	$configure($compiler);
	$compiler->compile();
};

if ($mode === 'disabled') {
	// The metadata storage is a lazy ghost which is never initialized, the opt-out breaks the whole Orm.
	Assert::error($compile, E_USER_WARNING, "%a%initializeMetadata: false%a%lazy%a%called too early%a%");
} elseif ($mode === 'enabled' || $mode === 'workaround') {
	// Only the deprecation of "initializeMetadata: true", see dic-initialize-metadata.phpt.
	Assert::error($compile, E_USER_DEPRECATED, "%a%initializeMetadata%a%deprecated%a%");
} else {
	Assert::noError($compile);
}

$loader = new ContainerLoader($cacheDir);
$className = @$loader->load($configure, __FILE__ . ':' . $mode); // @ - the compile-time errors are asserted above

/** @var Container $container */
$container = new $className();
$container->initialize();

$loadedMetadata = new ReflectionProperty(MetadataStorage::class, 'metadata');

if ($mode === 'disabled') {
	Assert::exception(fn () => new Author(), InvalidStateException::class, '%a%called too early%a%');

} else {
	// Default, the deprecated "true" and the pre-6.0 workaround: nothing is loaded until the first use.
	Assert::false($container->isCreated('nextras.orm.metadataStorage'));
	Assert::same([], $loadedMetadata->getValue());

	// The model is never touched, entity metadata have to be available anyway.
	Assert::noError(fn () => new Author());
	Assert::type(EntityMetadata::class, MetadataStorage::get(Author::class));

	// Only the used entities are loaded.
	$loadedCount = count($loadedMetadata->getValue());
	$storage = $container->getService('nextras.orm.metadataStorage');
	Assert::type(MetadataStorage::class, $storage);
	Assert::true($loadedCount < count($storage->getLoadedMetadata()));
	$allCount = count($loadedMetadata->getValue());

	// Deploy-time cache warm-up in a fresh process: load() initializes the lazy service and loads all metadata.
	foreach (['metadata', 'pending', 'loaders'] as $property) {
		(new ReflectionProperty(MetadataStorage::class, $property))->setValue(null, []);
	}
	$container = new $className();
	$container->initialize();
	$storage = $container->getByType(MetadataStorage::class);
	Assert::same($mode !== 'workaround', (new ReflectionClass($storage))->isUninitializedLazyObject($storage));
	$storage->load();
	Assert::false((new ReflectionClass($storage))->isUninitializedLazyObject($storage));
	Assert::same($allCount, count($loadedMetadata->getValue()));
}

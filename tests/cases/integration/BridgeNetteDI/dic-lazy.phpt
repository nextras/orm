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
use ReflectionProperty;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


$args = Environment::loadData();
$mode = $args['initializeMetadata'];

$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-lazy-' . $mode;
$loader = new ContainerLoader($cacheDir);
$className = $loader->load(function (Compiler $compiler) use ($cacheDir, $mode): ?string {
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
}, __FILE__ . ':' . $mode);

/** @var Container $container */
$container = new $className();
$container->initialize();

$loadedMetadata = new ReflectionProperty(MetadataStorage::class, 'metadata');

if ($mode === 'disabled') {
	Assert::exception(fn () => new Author(), InvalidStateException::class, '%a%called too early%a%');

} elseif ($mode === 'default') {
	// Nothing is loaded until the first use.
	Assert::false($container->isCreated('nextras.orm.metadataStorage'));
	Assert::same([], $loadedMetadata->getValue());

	// The model is never touched, entity metadata have to be available anyway.
	Assert::noError(fn () => new Author());
	Assert::type(EntityMetadata::class, MetadataStorage::get(Author::class));

} else {
	// Eagerly loaded during the container initialization.
	Assert::notSame([], $loadedMetadata->getValue());
	Assert::noError(fn () => new Author());
	Assert::type(EntityMetadata::class, MetadataStorage::get(Author::class));
}

<?php

namespace NextrasTests\Orm;

use Nette;
use Nette\DI\ContainerBuilder;


class Extension extends Nette\DI\CompilerExtension
{
	public $defaults = array(
		'container' => array(
			'debugger' => FALSE,
			'accessors' => TRUE,
		),
		'debugger' => array(
			'email' => NULL,
			'editor' => NULL,
			'browser' => NULL,
			'strictMode' => NULL,
			'maxLen' => NULL,
			'maxDepth' => NULL,
			'showLocation' => NULL,
			'scream' => NULL,
			'bar' => array(), // of class name
			'blueScreen' => array(), // of callback
		),
	);


	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$this->setupCache($container);
	}


	private function setupCache(ContainerBuilder $container)
	{
		$container->addDefinition($this->prefix('cacheJournal'))
			->setClass('Nette\Caching\Storages\FileJournal', array($container->expand('%tempDir%')));

		$container->addDefinition('cacheStorage') // no namespace for back compatibility
			->setClass('Nette\Caching\Storages\FileStorage', array($container->expand('%tempDir%/cache')));

		if (class_exists('Nette\Caching\Storages\PhpFileStorage')) {
			$container->addDefinition($this->prefix('templateCacheStorage'))
				->setClass('Nette\Caching\Storages\PhpFileStorage', array($container->expand('%tempDir%/cache')))
				->addSetup('::trigger_error', array('Service templateCacheStorage is deprecated.', E_USER_DEPRECATED))
				->setAutowired(FALSE);
		}

		$container->addDefinition($this->prefix('cache'))
			->setClass('Nette\Caching\Cache', array(1 => $container::literal('$namespace')))
			->addSetup('::trigger_error', array('Service cache is deprecated.', E_USER_DEPRECATED))
			->setParameters(array('namespace' => NULL));
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->methods['initialize'];
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		// debugger
		$initialize->addBody('Nette\Bridges\Framework\TracyBridge::initialize();');


		if ($container->parameters['debugMode']) {
			if ($config['container']['debugger']) {
				$config['debugger']['bar'][] = 'Nette\Bridges\DITracy\ContainerPanel';
			}

			foreach ((array) $config['debugger']['bar'] as $item) {
				$initialize->addBody($container->formatPhp(
					'Tracy\Debugger::getBar()->addPanel(?);',
					Nette\DI\Compiler::filterArguments(array(is_string($item) ? new Nette\DI\Statement($item) : $item))
				));
			}
		}

		foreach ((array) $config['debugger']['blueScreen'] as $item) {
			$initialize->addBody($container->formatPhp(
					'Tracy\Debugger::getBlueScreen()->addPanel(?);',
				Nette\DI\Compiler::filterArguments(array($item))
			));
		}

		if (!empty($container->parameters['tempDir'])) {
			$initialize->addBody('Nette\Caching\Storages\FileStorage::$useDirectories = ?;', array($this->checkTempDir($container->expand('%tempDir%/cache'))));
		}

		foreach ($container->findByTag('run') as $name => $on) {
			if ($on) {
				$initialize->addBody('$this->getService(?);', array($name));
			}
		}

		$initialize->addBody('Nette\Utils\SafeStream::register();');
		$initialize->addBody('Nette\Reflection\AnnotationsParser::setCacheStorage($this->getByType("Nette\Caching\IStorage"));');
		$initialize->addBody('Nette\Reflection\AnnotationsParser::$autoRefresh = ?;', array($container->parameters['debugMode']));
	}


	private function checkTempDir($dir)
	{
		// checks whether directory is writable
		$uniq = uniqid('_', TRUE);
		if (!@mkdir("$dir/$uniq")) { // @ - is escalated to exception
			throw new Nette\InvalidStateException("Unable to write to directory '$dir'. Make this directory writable.");
		}

		// checks whether subdirectory is writable
		$isWritable = @file_put_contents("$dir/$uniq/_", '') !== FALSE; // @ - error is expected
		if ($isWritable) {
			unlink("$dir/$uniq/_");
		}
		rmdir("$dir/$uniq");
		return $isWritable;
	}

}

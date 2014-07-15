<?php

namespace Nextras\Orm\Tests;

use Tester\Environment;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

require_once __DIR__ . '/inc/Configurator.php';
require_once __DIR__ . '/inc/Extension.php';


define('TEMP_DIR', __DIR__ . '/tmp');
date_default_timezone_set('Europe/Prague');

if (!isset($setupMode)) {
	Environment::setup();
}

$configurator = new Configurator();
if (getenv(Environment::RUNNER) !== '1') {
	$configurator->enableDebugger(TEMP_DIR . '/log');
}
$configurator->setTempDirectory(TEMP_DIR);
$configurator->addConfig(__DIR__ . '/config.neon');
$configurator->addConfig(__DIR__ . '/config.local.neon');
$configurator->createRobotLoader()->addDirectory(__DIR__)->register();

return $configurator->createContainer();

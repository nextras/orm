<?php

namespace Nextras\Orm\Tests;

use Tester\Environment;
use Tester\Helpers;


if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

require_once __DIR__ . '/inc/Configurator.php';
require_once __DIR__ . '/inc/Extension.php';

define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());


date_default_timezone_set('Europe/Prague');
Environment::setup();
Helpers::purge(TEMP_DIR);


$configurator = new Configurator;
if (getenv(Environment::RUNNER) !== '1') {
	$configurator->enableDebugger();
}
$configurator->setTempDirectory(TEMP_DIR);
$configurator->addConfig(__DIR__ . '/config.neon');
$configurator->addConfig(__DIR__ . '/config.local.neon');

$loader = $configurator->createRobotLoader();
$loader->addDirectory(__DIR__);
$loader->register();

return $configurator->createContainer();

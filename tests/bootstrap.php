<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nette\Configurator;
use Tester\Environment;


if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

require_once __DIR__ . '/inc/Helper.php';


define('TEMP_DIR', __DIR__ . '/tmp');
date_default_timezone_set('Europe/Prague');

Environment::setup();
Helper::check();

$section = Helper::getSection();
$configurator = new Configurator();

if (!Helper::isRunByRunner()) {
	$configurator->enableDebugger(__DIR__ . '/log');
} else {
	header('Content-type: text/plain');
	putenv('ANSICON=TRUE');
}

$configurator->addParameters(['container' => ['class' => "{$section}SystemContainer"]]);
$configurator->setTempDirectory(TEMP_DIR);
$configurator->addConfig(__DIR__ . "/config.$section.neon");

return $configurator->createContainer();

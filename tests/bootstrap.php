<?php

namespace Nextras\Orm\Tests;

use Nette\Caching\Storages\FileStorage;
use Tester\Environment;
use Tester\Helpers;


if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

require_once __DIR__ . '/TestCase.php';

date_default_timezone_set('Europe/Prague');
Environment::setup();


define('TEMP_DIR', __DIR__ . '/../tmp/' . getmypid());
@mkdir(dirname(TEMP_DIR)); // @ - directory may already exist
Helpers::purge(TEMP_DIR);


$cacheStorage = new FileStorage(TEMP_DIR);

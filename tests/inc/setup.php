<?php

use Nette\Database\Connection;
use Nette\Database\Helpers;
use Nette\DI\Container;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

/** @var Container $container */
/** @var Connection $database */


$setupMode = TRUE;

echo "[setup] Purging temp.\n";
@mkdir(__DIR__ . '/../tmp');
Tester\Helpers::purge(__DIR__ . '/../tmp');


$container = require_once __DIR__ . '/../bootstrap.php';
$config = $container->parameters['database'];
$database = new Connection("{$config['driver']}:host={$config['server']}", $config['username'], $config['password']);

echo "[setup] Bootstraping database structure.\n";
Helpers::loadFromFile($database, __DIR__ . '/../db/mysql-init.sql');


echo "[setup] All done.\n\n";

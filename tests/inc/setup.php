<?php

use Nette\Database\Connection;
use Nette\Database\Helpers;
use Nette\DI\Container;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

/** @var Container $container */
/** @var Connection $connection */


$setupMode = TRUE;

echo "[setup] Purging temp.\n";
@mkdir(__DIR__ . '/../tmp');
Tester\Helpers::purge(__DIR__ . '/../tmp');


$config = parse_ini_file(__DIR__ . '/../databases.ini', TRUE);
foreach ($config as $database => $options) {
	echo "[setup] Bootstraping '{$database}' database structure.\n";

	$connection = new Connection(
		$options['database_dsn'],
		$options['database_username'],
		$options['database_password']
	);

	Helpers::loadFromFile($connection, __DIR__ . "/../db/{$database}-init.sql");
}


echo "[setup] All done.\n\n";

<?php declare(strict_types = 1);


use Nextras\Dbal\Connection;
use Nextras\Dbal\Utils\FileImporter;


require_once __DIR__ . '/../../vendor/autoload.php';

echo "[setup] Purging temp.\n";
@mkdir(__DIR__ . '/../tmp');
Tester\Helpers::purge(__DIR__ . '/../tmp');

$config = parse_ini_file(__DIR__ . '/../databases.ini', true);
$config = $config === false ? [] : $config;

foreach ($config as $name => $configDatabase) {
	echo "[setup] Bootstrapping {$name} structure.\n";
	if ($name === 'array') continue;

	$connection = new Connection($configDatabase);
	$platform = $connection->getPlatform()->getName();

	/** @var callable $resetFunction */
	$resetFunction = require __DIR__ . "/../db/{$platform}-reset.php";
	$resetFunction($connection, $configDatabase['database']);

	FileImporter::executeFile($connection, __DIR__ . "/../db/{$platform}-init.sql");
}

echo "[setup] All done.\n\n";

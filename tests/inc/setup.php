<?php

use Nette\DI\Container;
use Nette\Neon\Neon;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Utils\FileImporter;
use Nextras\Orm\InvalidStateException;


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}

/** @var Container $container */
/** @var Connection $connection */


$setupMode = true;

echo "[setup] Purging temp.\n";
@mkdir(__DIR__ . '/../tmp');
Tester\Helpers::purge(__DIR__ . '/../tmp');


$sections = array_keys(parse_ini_file(__DIR__ . '/../sections.ini', true));

foreach ($sections as $section) {
	echo "[setup] Bootstraping '{$section}' structure.\n";
	$config = Neon::decode(file_get_contents(__DIR__ . "/../config.$section.neon", true));

	switch ($section) {
		case 'mysql':
		case 'pgsql':
			$connection = new Connection($config['nextras.dbal']);

			/** @var callable $resetFunction */
			$resetFunction = require __DIR__ . "/../db/{$section}-reset.php";
			$resetFunction($connection, $config['nextras.dbal']['database']);

			FileImporter::executeFile($connection, __DIR__ . "/../db/{$section}-init.sql");
			break;

		case 'array':
			break;

		default:
			throw new InvalidStateException();
	}
}


echo "[setup] All done.\n\n";

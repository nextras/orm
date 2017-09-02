<?php declare(strict_types = 1);

use Nette\DI\Container;
use Nette\Neon\Neon;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Utils\FileImporter;
use Nextras\Orm\InvalidStateException;
use NextrasTests\Orm\Helper;


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
		case Helper::SECTION_MYSQL:
		case Helper::SECTION_PGSQL:
		case Helper::SECTION_MSSQL:
			$connection = new Connection($config['nextras.dbal']);

			/** @var callable $resetFunction */
			$resetFunction = require __DIR__ . "/../db/{$section}-reset.php";
			$resetFunction($connection, $config['nextras.dbal']['database']);

			FileImporter::executeFile($connection, __DIR__ . "/../db/{$section}-init.sql");
			break;

		case Helper::SECTION_ARRAY:
			break;

		default:
			throw new InvalidStateException();
	}
}


echo "[setup] All done.\n\n";

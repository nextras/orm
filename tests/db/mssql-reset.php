<?php declare(strict_types = 1);


use Nextras\Dbal\IConnection;


return function (IConnection $connection, $dbname) {
	$connection->reconnectWithConfig(
		['database' => 'tempdb'] + $connection->getConfig()
	);
	$connection->query('DROP DATABASE IF EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE %table', $dbname);
	$connection->reconnectWithConfig(
		['database' => $dbname] + $connection->getConfig()
	);
};

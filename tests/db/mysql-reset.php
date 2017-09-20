<?php declare(strict_types = 1);

use Nextras\Dbal\IConnection;


return function (IConnection $connection, $dbname) {
	$connection->query('DROP DATABASE IF EXISTS %table', $dbname);
	$connection->query('CREATE DATABASE IF NOT EXISTS %table', $dbname);
	$connection->query('USE %table', $dbname);
};

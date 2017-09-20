<?php declare(strict_types = 1);

use Nextras\Dbal\IConnection;


return function (IConnection $connection, $dbname) {
	$connection->reconnectWithConfig([
		'database' => 'tempdb'
	] + $connection->getConfig());
	$connection->query('DROP DATABASE nextras_orm_test');
	$connection->query('CREATE DATABASE nextras_orm_test');
	$connection->reconnectWithConfig([
		'database' => 'nextras_orm_test',
	] + $connection->getConfig());
};

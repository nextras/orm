<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Nette\Caching\Storages\DevNullStorage;
use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection;
use Nextras\Orm\Mapper\Dbal\StorageReflection\UnderscoredStorageReflection;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class StorageReflectionTest extends TestCase
{

	public function testMismatchPrimaryKeys()
	{
		$platform = Mockery::mock('Nextras\Dbal\Platform\IPlatform');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'user_id' => ['is_primary' => TRUE],
			'group_id' => ['is_primary' => TRUE],
		]);

		$connection = Mockery::mock('Nextras\Dbal\Connection');
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->twice()->andReturn($platform);

		$cacheStorage = new DevNullStorage();

		Assert::throws(function() use ($connection, $cacheStorage) {
			new UnderscoredStorageReflection(
				$connection,
				'table_name',
				['id'],
				$cacheStorage
			);
		}, 'Nextras\Orm\InvalidStateException', 'Mismatch count of entity primary key (id) with storage primary key (user_id, group_id).');
	}


	public function testForeignKeysMappingUnderscored()
	{
		$platform = Mockery::mock('Nextras\Dbal\Platform\IPlatform');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([
			'user_id' => [],
			'group' => [],
		]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE],
			'user_id' => ['is_primary' => FALSE],
			'group' => ['is_primary' => FALSE],
		]);

		$connection = Mockery::mock('Nextras\Dbal\Connection');
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->twice()->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], $cacheStorage);

		Assert::same('user', $reflection->convertStorageToEntityKey('user_id'));
		Assert::same('group', $reflection->convertStorageToEntityKey('group'));

		Assert::same('user_id', $reflection->convertEntityToStorageKey('user'));
		Assert::same('group', $reflection->convertEntityToStorageKey('group'));
	}


	public function testForeignKeysMappingCamelized()
	{
		$platform = Mockery::mock('Nextras\Dbal\Platform\IPlatform');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([
			'userId' => [],
			'group' => [],
		]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE],
			'userId' => ['is_primary' => FALSE],
			'group' => ['is_primary' => FALSE],
		]);

		$connection = Mockery::mock('Nextras\Dbal\Connection');
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->twice()->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new CamelCaseStorageReflection($connection, 'table_name', ['id'], $cacheStorage);

		Assert::same('user', $reflection->convertStorageToEntityKey('userId'));
		Assert::same('group', $reflection->convertStorageToEntityKey('group'));

		Assert::same('userId', $reflection->convertEntityToStorageKey('user'));
		Assert::same('group', $reflection->convertEntityToStorageKey('group'));
	}


	public function testConvertCallbacks()
	{
		$platform = Mockery::mock('Nextras\Dbal\Platform\IPlatform');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE],
			'is_active' => ['is_primary' => FALSE],
		]);

		$connection = Mockery::mock('Nextras\Dbal\Connection');
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->twice()->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], $cacheStorage);
		$reflection->addMapping(
			'isActive',
			'is_active',
			function($val) { return $val ? 'Yes' : NULL; },
			function($val, & $key) {
				$key .= '%b';
				return $val;
			}
		);

		$result = $reflection->convertStorageToEntity([
			'id' => 2,
			'is_active' => 1,
		]);

		Assert::same([
			'id' => 2,
			'isActive' => 'Yes',
		], $result);

		$result = $reflection->convertEntityToStorage($result);

		Assert::same([
			'id' => 2,
			'is_active%b' => 'Yes',
		], $result);
	}

}


$test = new StorageReflectionTest($dic);
$test->run();

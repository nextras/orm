<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Nette\Caching\Storages\DevNullStorage;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection;
use Nextras\Orm\Mapper\Dbal\StorageReflection\UnderscoredStorageReflection;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class StorageReflectionTest extends TestCase
{

	public function testMismatchPrimaryKeys()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->twice()->with('table_name')->andReturn([
			'user_id' => ['is_primary' => TRUE, 'type' => 'int'],
			'group_id' => ['is_primary' => TRUE, 'type' => 'int'],
		]);

		$connection = Mockery::mock(Connection::class);
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->times(4)->andReturn($platform);

		$cacheStorage = new DevNullStorage();

		Assert::throws(function () use ($connection, $cacheStorage) {
			new UnderscoredStorageReflection(
				$connection,
				'table_name',
				['id'],
				$cacheStorage
			);
		}, InvalidStateException::class, 'Mismatch count of entity primary key (id) with storage primary key (user_id, group_id).');
	}


	public function testForeignKeysMappingUnderscored()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([
			'user_id' => [],
			'group' => [],
		]);
		$platform->shouldReceive('getColumns')->twice()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE, 'type' => 'int'],
			'user_id' => ['is_primary' => FALSE, 'type' => 'int'],
			'group' => ['is_primary' => FALSE, 'type' => 'int'],
		]);

		$connection = Mockery::mock(Connection::class);
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->times(4)->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], $cacheStorage);

		Assert::same('user', $reflection->convertStorageToEntityKey('user_id'));
		Assert::same('group', $reflection->convertStorageToEntityKey('group'));

		Assert::same('user_id', $reflection->convertEntityToStorageKey('user'));
		Assert::same('group', $reflection->convertEntityToStorageKey('group'));
	}


	public function testForeignKeysMappingCamelized()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([
			'userId' => [],
			'group' => [],
		]);
		$platform->shouldReceive('getColumns')->twice()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE, 'type' => 'int'],
			'userId' => ['is_primary' => FALSE, 'type' => 'int'],
			'group' => ['is_primary' => FALSE, 'type' => 'int'],
		]);

		$connection = Mockery::mock(Connection::class);
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->times(4)->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new CamelCaseStorageReflection($connection, 'table_name', ['id'], $cacheStorage);

		Assert::same('user', $reflection->convertStorageToEntityKey('userId'));
		Assert::same('group', $reflection->convertStorageToEntityKey('group'));

		Assert::same('userId', $reflection->convertEntityToStorageKey('user'));
		Assert::same('group', $reflection->convertEntityToStorageKey('group'));
	}


	public function testConvertCallbacks()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->twice()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE, 'type' => 'int'],
			'is_active' => ['is_primary' => FALSE, 'type' => 'int'],
		]);

		$connection = Mockery::mock(Connection::class);
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->times(4)->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], $cacheStorage);
		$reflection->addMapping(
			'isActive',
			'is_active',
			function ($val) { return $val ? 'Yes' : NULL; },
			function ($val, & $key) {
				$key .= '%b';
				return (bool) $val;
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
			'is_active%b' => TRUE,
		], $result);
	}


	public function testDbalModifiers()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->twice()->with('table_name')->andReturn([
			'id' => ['is_primary' => TRUE, 'type' => 'int'],
			'is_active' => ['is_primary' => FALSE, 'type' => 'int'],
		]);

		$connection = Mockery::mock(Connection::class);
		$connection->shouldReceive('getConfig')->once()->andReturn(['a']);
		$connection->shouldReceive('getPlatform')->times(4)->andReturn($platform);

		$cacheStorage = new DevNullStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], $cacheStorage);
		$reflection->addModifier('is_active', '%b');

		$result = $reflection->convertStorageToEntity([
			'is_active' => 1,
		]);

		Assert::same([
			'isActive' => 1,
		], $result);

		$result = $reflection->convertEntityToStorage($result);

		Assert::same([
			'is_active%b' => 1,
		], $result);
	}

}


$test = new StorageReflectionTest($dic);
$test->run();

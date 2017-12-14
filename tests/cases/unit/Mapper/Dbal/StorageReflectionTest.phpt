<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Dbal\IConnection;
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
		$platform->shouldReceive('getName')->andReturn('mysql');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'user_id' => ['is_primary' => true, 'type' => 'int'],
			'group_id' => ['is_primary' => true, 'type' => 'int'],
		]);

		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('getPlatform')->once()->andReturn($platform);

		$cacheStorage = new MemoryStorage();

		Assert::throws(function () use ($connection, $cacheStorage) {
			new UnderscoredStorageReflection(
				$connection,
				'table_name',
				['id'],
				new Cache($cacheStorage)
			);
		}, InvalidStateException::class, 'Mismatch count of entity primary key (id) with storage primary key (user_id, group_id).');
	}


	public function testForeignKeysMappingUnderscored()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getName')->andReturn('mysql');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([
			'user_id' => [],
			'group' => [],
		]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => true, 'type' => 'int'],
			'user_id' => ['is_primary' => false, 'type' => 'int'],
			'group' => ['is_primary' => false, 'type' => 'int'],
		]);

		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('getPlatform')->once()->andReturn($platform);

		$cacheStorage = new MemoryStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], new Cache($cacheStorage));

		Assert::same('user', $reflection->convertStorageToEntityKey('user_id'));
		Assert::same('group', $reflection->convertStorageToEntityKey('group'));

		Assert::same('user_id', $reflection->convertEntityToStorageKey('user'));
		Assert::same('group', $reflection->convertEntityToStorageKey('group'));
	}


	public function testForeignKeysMappingCamelized()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getName')->andReturn('mysql');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([
			'userId' => [],
			'group' => [],
		]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => true, 'type' => 'int'],
			'userId' => ['is_primary' => false, 'type' => 'int'],
			'group' => ['is_primary' => false, 'type' => 'int'],
		]);

		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('getPlatform')->once()->andReturn($platform);

		$cacheStorage = new MemoryStorage();
		$reflection = new CamelCaseStorageReflection($connection, 'table_name', ['id'], new Cache($cacheStorage));

		Assert::same('user', $reflection->convertStorageToEntityKey('userId'));
		Assert::same('group', $reflection->convertStorageToEntityKey('group'));

		Assert::same('userId', $reflection->convertEntityToStorageKey('user'));
		Assert::same('group', $reflection->convertEntityToStorageKey('group'));
	}


	public function testConvertCallbacks()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getName')->andReturn('mysql');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => true, 'type' => 'int'],
			'is_active' => ['is_primary' => false, 'type' => 'int'],
		]);

		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('getPlatform')->once()->andReturn($platform);

		$cacheStorage = new MemoryStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], new Cache($cacheStorage));
		$reflection->addMapping(
			'isActive',
			'is_active',
			function ($val) {
				return $val ? 'Yes' : null;
			},
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
			'is_active%b' => true,
		], $result);
	}


	public function testDbalModifiers()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getName')->andReturn('mysql');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'id' => ['is_primary' => true, 'type' => 'int'],
			'is_active' => ['is_primary' => false, 'type' => 'int'],
		]);

		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('getPlatform')->once()->andReturn($platform);

		$cacheStorage = new MemoryStorage();
		$reflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], new Cache($cacheStorage));
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


	public function testAddSetMappings()
	{
		$platform = Mockery::mock(IPlatform::class);
		$platform->shouldReceive('getName')->andReturn('mysql');
		$platform->shouldReceive('getForeignKeys')->once()->with('table_name')->andReturn([]);
		$platform->shouldReceive('getColumns')->once()->with('table_name')->andReturn([
			'bar' => ['is_primary' => true, 'type' => 'int'],
		]);

		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('getPlatform')->once()->andReturn($platform);

		$memoryStorage = new MemoryStorage();
		$storageReflection = new UnderscoredStorageReflection($connection, 'table_name', ['id'], new Cache($memoryStorage));

		Assert::same('bar', $storageReflection->convertEntityToStorageKey('id'));
		Assert::exception(function () use ($storageReflection) {
			$storageReflection->addMapping('id', 'another');
		}, InvalidStateException::class);

		$storageReflection->setMapping('id', 'foo');
		Assert::same('foo', $storageReflection->convertEntityToStorageKey('id'));
	}
}


$test = new StorageReflectionTest($dic);
$test->run();

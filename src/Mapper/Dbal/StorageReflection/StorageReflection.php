<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;


abstract class StorageReflection extends Object implements IStorageReflection
{
	/** @const keys for mapping cache */
	const TO_STORAGE = 0;
	const TO_ENTITY = 1;

	/** @var string */
	public $manyHasManyStorageNamePattern = '%s_x_%s';

	/** @var string */
	protected $storageName;

	/** @var array */
	protected $mappings;

	/** @var array */
	protected $entityPrimaryKey = [];

	/** @var array */
	protected $storagePrimaryKey = [];

	/** @var Connection */
	protected $connection;

	/** @var Cache */
	protected $cache;


	public function __construct(Connection $connection, $storageName, array $entityPrimaryKey, IStorage $cacheStorage)
	{
		$this->connection = $connection;
		$this->storageName = $storageName;
		$this->entityPrimaryKey = $entityPrimaryKey;

		$config = $connection->getConfig();
		$key = md5(json_encode($config));

		$this->cache = new Cache($cacheStorage, 'Nextras.Orm.db_reflection.' . $key);
		$this->mappings = $this->getDefaultMappings();
	}


	public function getStorageName()
	{
		return $this->storageName;
	}


	public function getStoragePrimaryKey()
	{
		if (!$this->storagePrimaryKey) {
			$primaryKeys = [];
			foreach ($this->getColumns() as $column => $meta) {
				if ($meta['is_primary']) {
					$primaryKeys[] = $column;
				}
			}
			if (count($primaryKeys) === 0) {
				$this->invalidateCache();
				throw new InvalidArgumentException("Storage '$this->storageName' has not defined any primary key.");
			}
			$this->storagePrimaryKey = $primaryKeys;
		}

		return $this->storagePrimaryKey;
	}


	public function convertEntityToStorage($in)
	{
		$out = [];
		foreach ($in as $key => $val) {
			if (isset($this->mappings[self::TO_STORAGE][$key][0])) {
				$newKey = $this->mappings[self::TO_STORAGE][$key][0];
			} else {
				$newKey = $this->convertEntityToStorageKey($key);
			}
			if (isset($this->mappings[self::TO_STORAGE][$key][1])) {
				$converter = $this->mappings[self::TO_STORAGE][$key][1];
				$out[$newKey] = $converter($val, $newKey);
			} else {
				$out[$newKey] = $val;
			}
		}

		return $out;
	}


	public function convertStorageToEntity($in)
	{
		$out = [];
		foreach ($in as $key => $val) {
			if (isset($this->mappings[self::TO_ENTITY][$key][0])) {
				$newKey = $this->mappings[self::TO_ENTITY][$key][0];
			} else {
				$newKey = $this->convertStorageToEntityKey($key);
			}
			if (isset($this->mappings[self::TO_ENTITY][$key][1])) {
				$converter = $this->mappings[self::TO_ENTITY][$key][1];
				$out[$newKey] = $converter($val, $newKey);
			} else {
				$out[$newKey] = $val;
			}
		}

		return $out;
	}


	public function convertStorageToEntityKey($key)
	{
		if (!isset($this->mappings[self::TO_ENTITY][$key][0])) {
			$this->mappings[self::TO_ENTITY][$key] = [$this->formatEntityKey($key)];
		}

		return $this->mappings[self::TO_ENTITY][$key][0];
	}


	public function convertEntityToStorageKey($key)
	{
		if (!isset($this->mappings[self::TO_STORAGE][$key][0])) {
			$this->mappings[self::TO_STORAGE][$key] = [$this->formatStorageKey($key)];
		}

		return $this->mappings[self::TO_STORAGE][$key][0];
	}


	public function getPrimarySequenceName()
	{
		if ($this->connection->getPlatform() instanceof PostgreSqlPlatform) {
			$columns = $this->getColumns();
			foreach ($columns as $column) {
				if ($column['is_primary']) {
					return $column['sequence'];
				}
			}
		}

		return NULL;
	}


	public function getManyHasManyStorageName(IMapper $target)
	{
		return sprintf(
			$this->manyHasManyStorageNamePattern,
			$this->storageName,
			preg_replace('#^(.*\.)?(.*)$#', '$2', $target->getStorageReflection()->getStorageName())
		);
	}


	public function getManyHasManyStoragePrimaryKeys(IMapper $target)
	{
		$targetStorageRefleciton = $target->getStorageReflection();

		$one = $this->getStoragePrimaryKey()[0];
		$two = $targetStorageRefleciton->getStoragePrimaryKey()[0];
		if ($one !== $two) {
			return [$one, $two];
		}

		return $this->findManyHasManyPrimaryColumns($this->getManyHasManyStorageName($target), $this->storageName, $targetStorageRefleciton->getStorageName());
	}


	/**
	 * Adds mapping.
	 * @param  string   $entity
	 * @param  string   $storage
	 * @param  callable $toEntityCb
	 * @param  callable $toStorageCb
	 * @return StorageReflection
	 */
	public function addMapping($entity, $storage, callable $toEntityCb = NULL, callable $toStorageCb = NULL)
	{
		$this->mappings[self::TO_ENTITY][$storage] = [$entity, $toEntityCb];
		$this->mappings[self::TO_STORAGE][$entity] = [$storage, $toStorageCb];
		return $this;
	}


	protected function findManyHasManyPrimaryColumns($joinTable, $sourceTable, $targetTable)
	{
		$useFQN = strpos($sourceTable, '.') !== FALSE;
		$keys = $this->getForeignKeys($joinTable);
		foreach ($keys as $column => $meta) {
			$table = $useFQN
				? $meta['ref_table']
				: preg_replace('#^(.*\.)?(.*)$#', '$2', $meta['ref_table']);

			if ($table === $sourceTable) {
				$sourceId = $column;
			} elseif ($table === $targetTable) {
				$targetId = $column;
			}
		}

		if (!isset($sourceId, $targetId)) {
			$this->invalidateCache();
			throw new InvalidStateException("No primary keys detected for many has many '{$joinTable}' join table.");
		}

		return [$sourceId, $targetId];
	}


	abstract protected function formatStorageKey($key);


	abstract protected function formatEntityKey($key);


	abstract protected function formatEntityForeignKey($key);


	private function invalidateCache()
	{
		$this->cache->clean();
	}


	/**
	 * @return array
	 */
	private function getDefaultMappings()
	{
		return $this->cache->load($this->storageName . '.mappings', function () {
			$this->mappings = [
				self::TO_STORAGE => [],
				self::TO_ENTITY => [],
			];

			$columns = array_keys($this->getForeignKeys($this->storageName));
			foreach ($columns as $column) {
				$this->addMapping($this->formatEntityForeignKey($column), $column);
			}

			$primaryKey = $this->getStoragePrimaryKey();

			if (count($this->entityPrimaryKey) !== count($primaryKey)) {
				throw new InvalidStateException(
					'Mismatch count of entity primary key (' . implode(', ', $this->entityPrimaryKey)
					. ') with storage primary key (' . implode(', ', $primaryKey) . ').'
				);
			}

			if ($this->entityPrimaryKey === ['id'] && count($primaryKey) === 1) {
				$this->addMapping('id', $primaryKey[0]);
			}

			return $this->mappings;
		});
	}


	private function getColumns()
	{
		return $this->cache->load($this->storageName . '.columns', function () {
			return $this->connection->getPlatform()->getColumns($this->storageName);
		});
	}


	private function getForeignKeys($table)
	{
		return $this->cache->load($table . '.fkeys', function () use ($table) {
			return $this->connection->getPlatform()->getForeignKeys($table);
		});
	}

}

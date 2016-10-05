<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;

use Nette\Caching\Cache;
use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\CachedPlatform;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotSupportedException;


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
	protected $modifiers;

	/** @var array */
	protected $entityPrimaryKey = [];

	/** @var array */
	protected $storagePrimaryKey = [];

	/** @var IPlatform */
	protected $platform;


	public function __construct(Connection $connection, $storageName, array $entityPrimaryKey, Cache $cache)
	{
		$this->platform = new CachedPlatform($connection, $cache->derive('db_reflection'));
		$this->storageName = $storageName;
		$this->entityPrimaryKey = $entityPrimaryKey;

		$cache = $cache->derive('storage_reflection');
		$this->mappings = $cache->load('nextras.orm.storage_reflection.' . md5($this->storageName) . '.mappings', function () {
			return $this->getDefaultMappings();
		});
		$this->modifiers = $cache->load('nextras.orm.storage_reflection.' . md5($this->storageName) . '.modifiers', function () {
			return $this->getDefaultModifiers();
		});
	}


	public function getStorageName()
	{
		return $this->storageName;
	}


	public function getStoragePrimaryKey()
	{
		if (!$this->storagePrimaryKey) {
			$primaryKeys = [];
			foreach ($this->platform->getColumns($this->storageName) as $column => $meta) {
				if ($meta['is_primary']) {
					$primaryKeys[] = $column;
				}
			}
			if (count($primaryKeys) === 0) {
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
			if (isset($this->modifiers[$newKey])) {
				$newKey .= $this->modifiers[$newKey];
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
		return $this->platform->getPrimarySequenceName($this->storageName);
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

		return $this->findManyHasManyPrimaryColumns(
			$this->getManyHasManyStorageName($target),
			$this->storageName,
			$targetStorageRefleciton->getStorageName()
		);
	}


	/**
	 * Adds mapping.
	 * @param  string   $entity
	 * @param  string   $storage
	 * @param  callable $toEntityCb
	 * @param  callable $toStorageCb
	 * @return StorageReflection
	 * @throws InvalidStateException Throws exception if mapping was already defined.
	 */
	public function addMapping($entity, $storage, callable $toEntityCb = null, callable $toStorageCb = null)
	{
		if (isset($this->mappings[self::TO_ENTITY][$storage])) {
			throw new InvalidStateException("Mapping for $storage column is already defined.");
		} elseif (isset($this->mappings[self::TO_STORAGE][$entity])) {
			throw new InvalidStateException("Mapping for $entity property is already defined.");
		}

		$this->mappings[self::TO_ENTITY][$storage] = [$entity, $toEntityCb];
		$this->mappings[self::TO_STORAGE][$entity] = [$storage, $toStorageCb];
		return $this;
	}


	/**
	 * Sets mapping.
	 * @param  string   $entity
	 * @param  string   $storage
	 * @param  callable $toEntityCb
	 * @param  callable $toStorageCb
	 * @return StorageReflection
	 */
	public function setMapping($entity, $storage, callable $toEntityCb = null, callable $toStorageCb = null)
	{
		unset($this->mappings[self::TO_ENTITY][$storage], $this->mappings[self::TO_STORAGE][$entity]);
		return $this->addMapping($entity, $storage, $toEntityCb, $toStorageCb);
	}


	/**
	 * Adds parameter modifier for data-trasform to Nextras Dbal layer.
	 * @param  string $storageKey
	 * @param  string $saveModifier
	 * @return StorageReflection
	 */
	public function addModifier($storageKey, $saveModifier)
	{
		$this->modifiers[$storageKey] = $saveModifier;
		return $this;
	}


	protected function findManyHasManyPrimaryColumns($joinTable, $sourceTable, $targetTable)
	{
		$useFQN = strpos($sourceTable, '.') !== false;
		$keys = $this->platform->getForeignKeys($joinTable);
		foreach ($keys as $column => $meta) {
			$table = $useFQN
				? $meta['ref_table']
				: preg_replace('#^(.*\.)?(.*)$#', '$2', $meta['ref_table']);

			if ($table === $sourceTable and !isset($sourceId)) {
				$sourceId = $column;
			} elseif ($table === $targetTable) {
				$targetId = $column;
			}
		}

		if (!isset($sourceId, $targetId)) {
			throw new InvalidStateException("No primary keys detected for many has many '{$joinTable}' join table.");
		}

		return [$sourceId, $targetId];
	}


	protected function getDefaultMappings()
	{
		$mappings = [self::TO_STORAGE => [], self::TO_ENTITY => []];

		$columns = array_keys($this->platform->getForeignKeys($this->storageName));
		foreach ($columns as $storageKey) {
			$entityKey = $this->formatEntityForeignKey($storageKey);
			$mappings[self::TO_ENTITY][$storageKey] = [$entityKey, null];
			$mappings[self::TO_STORAGE][$entityKey] = [$storageKey, null];
		}

		$storagePrimaryKey = $this->getStoragePrimaryKey();
		if (count($this->entityPrimaryKey) !== count($storagePrimaryKey)) {
			throw new InvalidStateException(
				'Mismatch count of entity primary key (' . implode(', ', $this->entityPrimaryKey)
				. ') with storage primary key (' . implode(', ', $storagePrimaryKey) . ').'
			);
		}

		if (count($storagePrimaryKey) === 1) {
			$entityKey = $this->entityPrimaryKey[0];
			$storageKey = $storagePrimaryKey[0];
			$mappings[self::TO_ENTITY][$storageKey] = [$entityKey, null];
			$mappings[self::TO_STORAGE][$entityKey] = [$storageKey, null];
		}

		return $mappings;
	}


	protected function getDefaultModifiers()
	{
		$modifiers = [];

		switch ($this->platform->getName()) {
			case 'postgresql':
				$types = ['TIMESTAMP' => true];
				break;

			case 'mysql':
				$types = ['DATETIME' => true];
				break;

			default:
				throw new NotSupportedException();
		}

		foreach ($this->platform->getColumns($this->storageName) as $column) {
			if (isset($types[$column['type']])) {
				$modifiers[$column['name']] = $column['is_nullable'] ? '%?dts' : '%dts';
			}
		}

		return $modifiers;
	}


	abstract protected function formatStorageKey($key);


	abstract protected function formatEntityKey($key);


	abstract protected function formatEntityForeignKey($key);
}

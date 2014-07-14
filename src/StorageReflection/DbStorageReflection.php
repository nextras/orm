<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\StorageReflection;

use Nette\Database\IStructure;
use Nette\Object;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;


abstract class DbStorageReflection extends Object implements IDbStorageReflection
{
	/**
	 * @param  string   $string
	 * @return string
	 */
	public static function camelize($string)
	{
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
	}


	/**
	 * @param  string
	 * @return string
	 */
	public static function underscore($string)
	{
		return strtolower(preg_replace('#(\w)([A-Z])#', '$1_$2', $string));
	}


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

	/** @var IStructure */
	protected $databaseStructure;


	public function __construct(IStructure $databaseStructure)
	{
		$this->databaseStructure = $databaseStructure;
		$this->mappings = [self::TO_STORAGE => [], self::TO_ENTITY => []];
	}


	public function setStorageName($storageName)
	{
		$this->storageName = $storageName;
		$this->initForeignKeyMappings();

		if (!isset($this->mappings[self::TO_STORAGE]['id'])) {
			$primaryKey = $this->getStoragePrimaryKey();
			if (count($primaryKey) === 1) {
				$this->addMapping('id', $primaryKey[0]);
			}
		}
	}


	public function getStorageName()
	{
		return $this->storageName;
	}


	public function getEntityPrimaryKey()
	{
		if (!$this->entityPrimaryKey) {
			foreach ($this->getStoragePrimaryKey() as $key) {
				$this->entityPrimaryKey[] = $this->convertStorageToEntityKey($key);
			}
		}

		return $this->entityPrimaryKey;
	}


	public function getStoragePrimaryKey()
	{
		if (!$this->storagePrimaryKey) {
			$primaryKey = $this->databaseStructure->getPrimaryKey($this->getStorageName());
			if (!$primaryKey) {
				throw new InvalidArgumentException("Storage '{$this->getStorageName()}' has not defined any primary key.");
			}
			$this->storagePrimaryKey = (array) $primaryKey;
		}

		return $this->storagePrimaryKey;
	}


	public function convertEntityToStorage($in)
	{
		$out = [];
		foreach ($in as $key => $val) {
			if (isset($this->mappings[self::TO_STORAGE][$key])) {
				$_key = $this->mappings[self::TO_STORAGE][$key];
			} else {
				$_key = $this->mappings[self::TO_STORAGE][$key] = $this->convertEntityToStorageKey($key);
			}
			$out[$_key] = $val;
		}

		return $out;
	}


	public function convertStorageToEntity($in)
	{
		$out = [];
		foreach ($in as $key => $val) {
			if (isset($this->mappings[self::TO_ENTITY][$key])) {
				$_key = $this->mappings[self::TO_ENTITY][$key];
			} else {
				$_key = $this->mappings[self::TO_ENTITY][$key] = $this->convertStorageToEntityKey($key);
			}
			$out[$_key] = $val;
		}

		return $out;
	}


	public function convertStorageToEntityKey($key)
	{
		if (!isset($this->mappings[self::TO_ENTITY][$key])) {
			$this->mappings[self::TO_ENTITY][$key] = $this->formatEntityKey($key);
		}

		return $this->mappings[self::TO_ENTITY][$key];
	}


	public function convertEntityToStorageKey($key)
	{
		if (!isset($this->mappings[self::TO_STORAGE][$key])) {
			$this->mappings[self::TO_STORAGE][$key] = $this->formatStorageKey($key);
		}

		return $this->mappings[self::TO_STORAGE][$key];
	}


	public function getManyHasManyStorageName(IMapper $target)
	{
		return sprintf($this->manyHasManyStorageNamePattern, $this->getStorageName(), $target->getStorageReflection()->getStorageName());
	}


	public function getManyHasManyStoragePrimaryKeys(IMapper $target)
	{
		$one = $this->getStoragePrimaryKey()[0];
		$two = $target->getStorageReflection()->getStoragePrimaryKey()[0];
		if ($one !== $two) {
			return [$one, $two];
		}

		return $this->findManyHasManyPrimaryColumns($this->getManyHasManyStorageName($target), $this->getStorageName(), $target->getTableName());
	}


	/**
	 * Adds mapping.
	 * @param  string   $entity
	 * @param  string   $storage
	 * @return self
	 */
	public function addMapping($entity, $storage)
	{
		$this->mappings[self::TO_ENTITY][$storage] = $entity;
		$this->mappings[self::TO_STORAGE][$entity] = $storage;
		return $this;
	}


	protected function initForeignKeyMappings()
	{
		$keys = $this->databaseStructure->getBelongsToReference($this->getStorageName());
		foreach ($keys as $column => $table) {
			$this->addMapping($this->formatEntityForeignKey($column), $column);
		}
	}


	protected function findManyHasManyPrimaryColumns($joinTable, $sourceTable, $targetTable)
	{
		$keys = $this->databaseStructure->getBelongsToReference($joinTable);
		foreach ($keys as $column => $table) {
			if ($table === $sourceTable) {
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


	abstract protected function formatStorageKey($key);


	abstract protected function formatEntityKey($key);


	abstract protected function formatEntityForeignKey($key);

}

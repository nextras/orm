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
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;


abstract class DbStorageReflection extends Object implements IDbStorageReflection
{

	/**
	 * @param  string
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


	/** @var string */
	public $manyHasManyStorageNamePattern = '%s_x_%s';

	/** @var IMapper */
	protected $mapper;

	/** @var array */
	protected $mappings = [];

	/** @var array */
	protected $entityPrimaryKey = [];

	/** @var array */
	protected $storagePrimaryKey = [];

	/** @var IStructure */
	protected $databaseStructure;


	public function __construct(IMapper $mapper, IStructure $databaseStructure)
	{
		$this->mapper = $mapper;
		$this->databaseStructure = $databaseStructure;

		$this->initForeignKeyMappings();

		if (!isset($this->mappings['toS']['id'])) {
			$primaryKey = $this->databaseStructure->getPrimaryKey($this->getStorageName());
			if (!is_array($primaryKey)) {
				$this->addMapping('id', $primaryKey);
			}
		}
	}


	public function getStorageName()
	{
		return static::underscore(substr($this->mapper->getReflection()->getShortName(), 0, -6));
	}


	public function getEntityPrimaryKey()
	{
		if (!$this->entityPrimaryKey) {
			$class = $this->mapper->getRepository()->getEntityClassNames()[0];
			$this->entityPrimaryKey = $this->mapper->getRepository()->getModel()->getMetadataStorage()->get($class)->primaryKey;
		}

		return $this->entityPrimaryKey;
	}


	public function getStoragePrimaryKey()
	{
		if (!$this->storagePrimaryKey) {
			foreach ($this->getEntityPrimaryKey() as $key) {
				$this->storagePrimaryKey[] = $this->convertEntityToStorageKey($key);
			}
		}

		return $this->storagePrimaryKey;
	}


	public function convertEntityToStorage($in)
	{
		$out = [];
		foreach ($in as $key => $val) {
			if (isset($this->mappings['toS'][$key])) {
				$_key = $this->mappings['toS'][$key];
			} else {
				$_key = $this->mappings['toS'][$key] = $this->convertEntityToStorageKey($key);
			}
			$out[$_key] = $val;
		}

		return $out;
	}


	public function convertStorageToEntity($in)
	{
		$out = [];
		foreach ($in as $key => $val) {
			if (isset($this->mappings['toE'][$key])) {
				$_key = $this->mappings['toE'][$key];
			} else {
				$_key = $this->mappings['toE'][$key] = $this->convertStorageToEntityKey($key);
			}
			$out[$_key] = $val;
		}

		return $out;
	}


	public function convertStorageToEntityKey($key)
	{
		if (!isset($this->mappings['toE'][$key])) {
			$this->mappings['toE'][$key] = $this->formatEntityKey($key);
		}

		return $this->mappings['toE'][$key];
	}


	public function convertEntityToStorageKey($key)
	{
		if (!isset($this->mappings['toS'][$key])) {
			$this->mappings['toS'][$key] = $this->formatStorageKey($key);
		}

		return $this->mappings['toS'][$key];
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

		return $this->findManyHasManyPrimaryColumns($this->getManyHasManyStorageName($target), $this->mapper->getTableName(), $target->getTableName());
	}


	/**
	 * Adds mapping.
	 * @param  string
	 * @param  string
	 * @return self
	 */
	public function addMapping($entity, $storage)
	{
		$this->mappings['toE'][$storage] = $entity;
		$this->mappings['toS'][$entity] = $storage;
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

<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper;

use Nette\Database\Context;
use Nette\Database\IDatabaseStructure;
use Nette\Database\Table\SqlBuilder;
use Nette\Database\Conventions\IConventions;
use Nextras\Orm\Entity\Collection\Collection;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\CollectionMapper\CollectionMapper;
use Nextras\Orm\Mapper\CollectionMapper\CollectionMapperHasOne;
use Nextras\Orm\Mapper\CollectionMapper\ICollectionMapper;
use Nextras\Orm\Mapper\CollectionMapper\CollectionMapperManyHasMany;
use Nextras\Orm\Mapper\CollectionMapper\CollectionMapperOneHasMany;
use Nextras\Orm\Mapper\CollectionMapper\SqlBuilderCollectionMapper;
use Nextras\Orm\StorageReflection\IDbStorageReflection;
use Nextras\Orm\StorageReflection\UnderscoredDbStorageReflection;


/**
 * Mapper for Nette\Database.
 */
class Mapper extends BaseMapper
{
	/** @var string */
	protected $tableName;

	/** @var Context */
	protected $databaseContext;

	/** @var IDatabaseStructure */
	protected $databaseStructure;

	/** @var IConventions */
	protected $databaseConventions;

	/** @var IDbStorageReflection */
	protected $storageReflection;

	/** @var array */
	protected $cacheCollectionMapper = [];

	/** @var array */
	private static $transactions = [];


	public function __construct(Context $databaseContext)
	{
		$this->databaseContext = $databaseContext;
		$this->databaseStructure = $databaseContext->getStructure();
		$this->databaseConventions = $databaseContext->getConventions();
	}


	public function findAll()
	{
		return $this->createCollection();
	}


	public function builder()
	{
		return new SqlBuilder($this->getTableName(), $this->databaseContext->getConnection(), $this->databaseConventions);
	}


	public function createCollection()
	{
		return new Collection($this->createCollectionMapper());
	}


	public function toCollection(SqlBuilder $builder)
	{
		return new Collection(new SqlBuilderCollectionMapper($this->getRepository(), $this->databaseContext, $builder));
	}


	// == Collection mappers ===========================================================================================


	public function createCollectionMapper($tableName = NULL)
	{
		return new CollectionMapper($this->getRepository(), $this->databaseContext, $tableName ?: $this->getTableName());
	}


	// == Relationship collection mappers ==============================================================================


	public function createCollectionOneHasMany(IMapper $mapper, PropertyMetadata $metadata, IEntity $parent)
	{
		$collectionMapper = $this->getCollectionMapperOneHasMany($mapper, $metadata);

		return new Collection(
			$this->createCollectionMapper(),
			function(ICollection $byCollectin) use ($collectionMapper, $parent) {
				return $collectionMapper->getIterator($parent, $byCollectin);
			},
			function(ICollection $byCollection) use ($collectionMapper, $parent) {
				return $collectionMapper->getIteratorCount($parent, $byCollection);
			}
		);
	}


	public function createCollectionManyHasMany(IMapper $mapper, PropertyMetadata $metadata, IEntity $parent)
	{
		$collectionMapper = $this->getCollectionMapperManyHasMany($mapper, $metadata);
		$targetMapper     = $metadata->args[2] ? $mapper : $this;

		return new Collection(
			$targetMapper->createCollectionMapper(),
			function(ICollection $byCollection) use ($collectionMapper, $parent) {
				return $collectionMapper->getIterator($parent, $byCollection);
			},
			function(ICollection $byCollection) use ($collectionMapper, $parent) {
				return $collectionMapper->getIteratorCount($parent, $byCollection);
			}
		);
	}


	public function getCollectionMapperHasOne(PropertyMetadata $metadata)
	{
		$c = & $this->cacheCollectionMapper[1][$metadata->name];

		if (empty($c)) {
			$targetMapper = $c[0] = $this->getRepository()->getModel()->getRepository($metadata->args[0])->getMapper();
			return $c[1] = $this->createCollectionMapperHasOne($targetMapper, $targetMapper->createCollectionMapper(), $metadata);
		}

		return $c[1];
	}


	public function getCollectionMapperManyHasMany(IMapper $mapper, PropertyMetadata $metadata)
	{
		$cache = & $this->cacheCollectionMapper[0][$metadata->name];

		if (!$cache) {
			$targetMapper = $metadata->args[2] ? $mapper : $this;
			$cache = $this->createCollectionMapperManyHasMany($mapper, $targetMapper->findAll(), $metadata);
		}

		return $cache;
	}


	public function getCollectionMapperOneHasMany(IMapper $mapper, PropertyMetadata $metadata)
	{
		$cache = & $this->cacheCollectionMapper[2][$metadata->name];

		if (!$cache) {
			return $cache = $this->createCollectionMapperOneHasMany($mapper, $this->findAll(), $metadata);
		}

		return $cache;
	}


	protected function createCollectionMapperHasOne(IMapper $targetMapper, ICollectionMapper $defaultMapper, PropertyMetadata $metadata)
	{
		return new CollectionMapperHasOne($this->databaseContext, $targetMapper, $defaultMapper, $metadata);
	}


	protected function createCollectionMapperManyHasMany(IMapper $mapperTwo, ICollection $defaultCollection, PropertyMetadata $metadata)
	{
		return new CollectionMapperManyHasMany($this->databaseContext, $this, $mapperTwo, $defaultCollection, $metadata);
	}


	protected function createCollectionMapperOneHasMany(IMapper $targetMapper, ICollection $defaultCollection, PropertyMetadata $metadata)
	{
		return new CollectionMapperOneHasMany($this->databaseContext, $targetMapper, $defaultCollection, $metadata);
	}


	// == Mapper configuration =========================================================================================


	public function getTableName()
	{
		if (!$this->tableName) {
			$this->tableName = $this->getStorageReflection()->getStorageName();
		}

		return $this->tableName;
	}


	public function getManyHasManyParameters(IMapper $mapper)
	{
		return [
			$this->storageReflection->getManyHasManyStorageName($mapper),
			$this->storageReflection->getManyHasManyStoragePrimaryKeys($mapper),
		];
	}


	public function getStorageReflection()
	{
		if ($this->storageReflection === NULL) {
			$this->storageReflection = $this->createStorageReflection();
		}

		return $this->storageReflection;
	}


	protected function createStorageReflection()
	{
		return new UnderscoredDbStorageReflection($this, $this->databaseStructure);
	}


	// == Persistence API ==============================================================================================


	public function persist(IEntity $entity)
	{
		$this->begin();
		$id = $entity->getValue('id', TRUE);
		$data = $entity->toArray();

		$storageProperties = $entity->getMetadata()->storageProperties;
		foreach ($data as $key => $value) {
			if (!in_array($key, $storageProperties, TRUE)) {
				unset($data[$key]);
			}
			if ($value instanceof IEntity)  {
				$data[$key] = $value->id;
			}
		}

		unset($data['id']);

		$data = $this->getStorageReflection()->convertEntityToStorage($data);

		if (!$id) {
			$this->databaseContext->query('INSERT INTO ' . $this->getTableName() . ' ', $data);
			return $this->databaseContext->getInsertId();
		} else {
			$primary = [];
			$id = (array) $id;
			foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
				$primary[$key] = array_shift($id);

			}
			$this->databaseContext->query('UPDATE ' . $this->getTableName() . ' SET', $data, 'WHERE ?', $primary);
			return $entity->id;
		}
	}


	public function remove(IEntity $entity)
	{
		$this->begin();

		$id = (array) $entity->id;
		$primary = [];
		foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
			$primary[$key] = array_shift($id);
		}

		$this->databaseContext->query('DELETE FROM ' . $this->getTableName() . ' WHERE ?', $primary);
	}


	// == Transactions API =============================================================================================


	public function flush()
	{
		$hash = spl_object_hash($this->databaseContext);
		if (isset(self::$transactions[$hash])) {
			$this->databaseContext->commit();
			unset(self::$transactions[$hash]);
		}
	}


	public function rollback()
	{
		$hash = spl_object_hash($this->databaseContext);
		if (isset(self::$transactions[$hash])) {
			$this->databaseContext->rollback();
			unset(self::$transactions[$hash]);
		}
	}


	protected function begin()
	{
		$hash = spl_object_hash($this->databaseContext);
		if (!isset(self::$transactions[$hash])) {
			$this->databaseContext->beginTransaction();
			self::$transactions[$hash] = TRUE;
		}
	}

}

<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Nette;

use Nette\Database\Context;
use Nette\Database\IConventions;
use Nette\Database\IStructure;
use Nette\Database\Table\SqlBuilder;
use Nextras\Orm\Entity\Collection\Collection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\StorageReflection\UnderscoredDbStorageReflection;


/**
 * Mapper for Nette\Database.
 */
class NetteMapper extends BaseMapper
{
	/** @var Context */
	protected $databaseContext;

	/** @var IStructure */
	protected $databaseStructure;

	/** @var IConventions */
	protected $databaseConventions;

	/** @var array */
	protected $cacheRM = [];

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


	public function getManyHasManyParameters(IMapper $mapper)
	{
		return [
			$this->storageReflection->getManyHasManyStorageName($mapper),
			$this->storageReflection->getManyHasManyStoragePrimaryKeys($mapper),
		];
	}


	protected function createCollectionMapper()
	{
		return new CollectionMapper($this->getRepository(), $this->databaseContext, $this->getTableName());
	}


	// == Relationship mappers =========================================================================================


	public function createCollectionHasOne(PropertyMetadata $metadata, IEntity $parent)
	{
		return new Collection(
			$this->createCollectionMapper(),
			$this->getRelationshipMapperHasOne($metadata),
			$parent
		);
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->args[2] ? $mapperTwo : $this;
		return new Collection(
			$targetMapper->createCollectionMapper(),
			$this->getRelationshipMapperManyHasMany($mapperTwo, $metadata),
			$parent
		);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent)
	{
		return new Collection(
			$this->createCollectionMapper(),
			$this->getRelationshipMapperOneHasMany($metadata),
			$parent
		);
	}


	public function getRelationshipMapperHasOne(PropertyMetadata $metadata)
	{
		if (!isset($this->cacheRM[0][$metadata->name])) {
			$this->cacheRM[0][$metadata->name] = $this->createRelationshipMapperHasOne($metadata);
		}

		return $this->cacheRM[0][$metadata->name];
	}


	public function getRelationshipMapperManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata)
	{
		if (!isset($this->cacheRM[1][$metadata->name])) {
			$this->cacheRM[1][$metadata->name] = $this->createRelationshipMapperManyHasMany($mapperTwo, $metadata);
		}

		return $this->cacheRM[1][$metadata->name];
	}


	public function getRelationshipMapperOneHasMany(PropertyMetadata $metadata)
	{
		if (!isset($this->cacheRM[2][$metadata->name])) {
			$this->cacheRM[2][$metadata->name] = $this->createRelationshipMapperOneHasMany($metadata);
		}

		return $this->cacheRM[2][$metadata->name];
	}


	protected function createRelationshipMapperHasOne(PropertyMetadata $metadata)
	{
		return new RelationshipMapperHasOne($this->databaseContext, $this, $metadata);
	}


	protected function createRelationshipMapperManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata)
	{
		return new RelationshipMapperManyHasMany($this->databaseContext, $this, $mapperTwo, $metadata);
	}


	protected function createRelationshipMapperOneHasMany(PropertyMetadata $metadata)
	{
		return new RelationshipMapperOneHasMany($this->databaseContext, $this, $metadata);
	}


	protected function createStorageReflection()
	{
		return new UnderscoredDbStorageReflection($this, $this->databaseStructure);
	}


	// == Persistence API ==============================================================================================


	public function persist(IEntity $entity)
	{
		if (!$entity->isModified()) {
			$entity->id;
		}

		$this->beginTransaction();
		$id = $entity->getValue('id', TRUE);
		$data = $entity->toArray(IEntity::TO_ARRAY_LOADED_RELATIONSHIP_AS_IS);

		$storageProperties = $entity->getMetadata()->storageProperties;
		foreach ($data as $key => $value) {
			if (!in_array($key, $storageProperties, TRUE) || $value instanceof IRelationshipCollection) {
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
		$this->beginTransaction();

		$id = (array) $entity->id;
		$primary = [];
		foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
			$primary[$key] = array_shift($id);
		}

		$this->databaseContext->query('DELETE FROM ' . $this->getTableName() . ' WHERE ?', $primary);
	}


	// == Transactions API =============================================================================================


	public function beginTransaction()
	{
		$hash = spl_object_hash($this->databaseContext);
		if (!isset(self::$transactions[$hash])) {
			$this->databaseContext->beginTransaction();
			self::$transactions[$hash] = TRUE;
		}
	}


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

}

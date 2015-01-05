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
use Nette\Database\ResultSet;
use Nette\Database\Table\Selection;
use Nette\Database\Table\SqlBuilder;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\Collection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\Database\IPropertyStorableConverter;
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\StorageReflection\UnderscoredDbStorageReflection;
use Nextras\Orm\InvalidArgumentException;


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


	public function table()
	{
		return $this->databaseContext->table($this->getTableName());
	}


	public function builder()
	{
		return new SqlBuilder($this->getTableName(), $this->databaseContext);
	}


	public function createCollection()
	{
		return new Collection($this->createCollectionMapper());
	}


	public function toCollection($data)
	{
		if ($data instanceof SqlBuilder) {
			return new Collection(new SqlBuilderCollectionMapper($this->getRepository(), $this->databaseContext, $data));

		} elseif ($data instanceof Selection) {
			return new Collection(new SqlBuilderCollectionMapper($this->getRepository(), $this->databaseContext, $data->getSqlBuilder()));

		} elseif (is_array($data) || $data instanceof ResultSet) {
			$result = [];
			$repository = $this->getRepository();
			foreach ($data as $row) {
				$result[] = $repository->hydrateEntity((array) $row);
			}
			return new ArrayCollection($result);

		} else {
			throw new InvalidArgumentException('NetteMapper can convert only array|Selection|SqlBuilder|ResultSet to ICollection, recieved "' . gettype($data) . '".');
		}
	}


	public function getManyHasManyParameters(IMapper $mapper)
	{
		return [
			$this->getStorageReflection()->getManyHasManyStorageName($mapper),
			$this->getStorageReflection()->getManyHasManyStoragePrimaryKeys($mapper),
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


	public function createCollectionOneHasOneDirected(PropertyMetadata $metadata, IEntity $parent)
	{
		if ($metadata->relationshipIsMain) {
			return new Collection(
				$this->createCollectionMapper(),
				$this->getRelationshipMapperHasOne($metadata),
				$parent
			);
		} else {
			return new Collection(
				$this->createCollectionMapper(),
				$this->getRelationshipMapperOneHasOneDirected($metadata),
				$parent
			);
		}
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->relationshipIsMain ? $mapperTwo : $this;
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
		$key = spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[0][$key])) {
			$this->cacheRM[0][$key] = $this->createRelationshipMapperHasOne($metadata);
		}

		return $this->cacheRM[0][$key];
	}


	public function getRelationshipMapperOneHasOneDirected($metadata)
	{
		$key = spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[1][$key])) {
			$this->cacheRM[1][$key] = $this->createRelationshipMapperOneHasOneDirected($metadata);
		}

		return $this->cacheRM[1][$key];
	}


	public function getRelationshipMapperManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata)
	{
		$key = spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[2][$key])) {
			$this->cacheRM[2][$key] = $this->createRelationshipMapperManyHasMany($mapperTwo, $metadata);
		}

		return $this->cacheRM[2][$key];
	}


	public function getRelationshipMapperOneHasMany(PropertyMetadata $metadata)
	{
		$key = spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[3][$key])) {
			$this->cacheRM[3][$key] = $this->createRelationshipMapperOneHasMany($metadata);
		}

		return $this->cacheRM[3][$key];
	}


	protected function createRelationshipMapperHasOne(PropertyMetadata $metadata)
	{
		return new RelationshipMapperHasOne($this->databaseContext, $this, $metadata);
	}


	protected function createRelationshipMapperOneHasOneDirected($metadata)
	{
		return new RelationshipMapperOneHasOneDirected($this->databaseContext, $this, $metadata);
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
		return new UnderscoredDbStorageReflection(
			$this->databaseStructure,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata()->getPrimaryKey()
		);
	}


	// == Persistence API ==============================================================================================


	public function persist(IEntity $entity)
	{
		$this->beginTransaction();

		$data = $this->entityToArray($entity);
		$id = $entity->getValue('id');
		if ($id === NULL || $entity->isPersisted()) {
			unset($data['id']);
		}
		$data = $this->getStorageReflection()->convertEntityToStorage($data);
		$driver = $this->databaseContext->getConnection()->getSupplementalDriver();
		$tableName = $driver->delimite($this->getTableName());

		if (!$entity->isPersisted()) {
			$this->databaseContext->query('INSERT INTO ' . $tableName . ' ', $data);
			if ($id) {
				return $id;
			} else {
				return $this->databaseContext->getInsertId($this->databaseStructure->getPrimaryKeySequence($this->getTableName()));
			}
		} else {
			$primary = [];
			$id = (array) $id;
			foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}

			$this->databaseContext->query('UPDATE ' . $tableName . ' SET', $data, 'WHERE ?', $primary);
			return $entity->id;
		}
	}


	public function remove(IEntity $entity)
	{
		$this->beginTransaction();

		$id = (array) $entity->getPersistedId();
		$primary = [];
		foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
			$primary[$key] = array_shift($id);
		}

		$driver = $this->databaseContext->getConnection()->getSupplementalDriver();
		$tableName = $driver->delimite($this->getTableName());
		$this->databaseContext->query('DELETE FROM ' . $tableName . ' WHERE ?', $primary);
	}


	protected function entityToArray(IEntity $entity)
	{
		$return = [];
		$metadata = $entity->getMetadata();

		foreach ($metadata->getProperties() as $name => $metadataProperty) {
			if ($metadataProperty->isVirtual) {
				continue;
			}

			$property = $entity->getProperty($name);
			if ($property instanceof IRelationshipCollection || $property instanceof IRelationshipContainer) {
				$meta = $metadata->getProperty($name);
				$type = $meta->relationshipType;
				if ($type === PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY || $type === PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY) {
					continue;
				} elseif ($type === PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED && !$meta->relationshipIsMain) {
					continue;
				}
			}

			if ($property instanceof IPropertyStorableConverter) {
				$value = $property->getDatabaseStorableValue();

			} else {
				$value = $entity->getValue($name);
			}

			$return[$name] = $value;
		}

		return $return;
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
		parent::flush();
		$this->cacheRM = [];
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

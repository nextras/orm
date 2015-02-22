<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\Collection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\Database\IPropertyStorableConverter;
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\InvalidArgumentException;


/**
 * Mapper for Nextras\Dbal.
 */
class DbalMapper extends BaseMapper
{
	/** @var Connection */
	protected $connection;

	/** @var array */
	protected $cacheRM = [];

	/** @var array */
	private static $transactions = [];


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}


	public function findAll()
	{
		return $this->createCollection();
	}


	public function builder()
	{
		$tableName = $this->getTableName();
		$builder = new QueryBuilder($this->connection->getDriver());
		$builder->from("[$tableName]", QueryBuilderHelper::getAlias($tableName));
		return $builder;
	}


	public function createCollection()
	{
		return new Collection($this->createCollectionMapper());
	}


	public function toCollection($data)
	{
		if ($data instanceof QueryBuilder) {
			return new Collection(new QueryBuilderCollectionMapper($this->getRepository(), $this->connection, $data));

		} elseif (is_array($data) || $data instanceof Result) {
			$result = [];
			$repository = $this->getRepository();
			foreach ($data as $row) {
				$result[] = $repository->hydrateEntity($row->toArray());//todo: fix
			}
			return new ArrayCollection($result);

		} else {
			throw new InvalidArgumentException('DbalMapper can convert only array|Selection|SqlBuilder|ResultSet to ICollection, recieved "' . gettype($data) . '".');
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
		return new CollectionMapper($this->getRepository(), $this->connection, $this->getTableName());
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
		return new RelationshipMapperHasOne($this->connection, $this, $metadata);
	}


	protected function createRelationshipMapperOneHasOneDirected($metadata)
	{
		return new RelationshipMapperOneHasOneDirected($this->connection, $this, $metadata);
	}


	protected function createRelationshipMapperManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata)
	{
		return new RelationshipMapperManyHasMany($this->connection, $this, $mapperTwo, $metadata);
	}


	protected function createRelationshipMapperOneHasMany(PropertyMetadata $metadata)
	{
		return new RelationshipMapperOneHasMany($this->connection, $this, $metadata);
	}


	/**
	 * @return StorageReflection\IStorageReflection
	 */
	public function getStorageReflection()
	{
		return parent::getStorageReflection();
	}


	protected function createStorageReflection()
	{
		return new StorageReflection\UnderscoredStorageReflection(
			$this->connection,
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

		if (!$entity->isPersisted()) {
			$this->connection->query('INSERT INTO %table %values', $this->getTableName(), $data);
			return $id ?: $this->connection->getLastInsertedId($this->getStorageReflection()->getPrimarySequenceName());

		} else {
			$primary = [];
			$id = (array) $id;
			foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}

			$this->connection->query('UPDATE %table SET %set WHERE %and', $this->getTableName(), $data, $primary);
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

		$this->connection->query('DELETE FROM %table WHERE %and', $this->getTableName(), $primary);
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
		$hash = spl_object_hash($this->connection);
		if (!isset(self::$transactions[$hash])) {
			$this->connection->transactionBegin();
			self::$transactions[$hash] = TRUE;
		}
	}


	public function flush()
	{
		parent::flush();
		$this->cacheRM = [];
		$hash = spl_object_hash($this->connection);
		if (isset(self::$transactions[$hash])) {
			$this->connection->transactionCommit();
			unset(self::$transactions[$hash]);
		}
	}


	public function rollback()
	{
		$hash = spl_object_hash($this->connection);
		if (isset(self::$transactions[$hash])) {
			$this->connection->transactionRollback();
			unset(self::$transactions[$hash]);
		}
	}

}

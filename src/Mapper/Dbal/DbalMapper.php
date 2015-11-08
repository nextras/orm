<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Caching\IStorage;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
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

	/** @var IStorage */
	protected $cacheStorage;

	/** @var array */
	private $cacheRM = [];

	/** @var array */
	private static $transactions = [];


	public function __construct(Connection $connection, IStorage $cacheStorage)
	{
		$this->connection = $connection;
		$this->cacheStorage = $cacheStorage;
	}


	/** @inheritdoc */
	public function findAll()
	{
		return new DbalCollection($this->getRepository(), $this->connection, $this->builder());
	}


	public function builder()
	{
		$tableName = $this->getTableName();
		$builder = new QueryBuilder($this->connection->getDriver());
		$builder->from("[$tableName]", QueryBuilderHelper::getAlias($tableName));
		return $builder;
	}


	/** @inheritdoc */
	public function toCollection($data)
	{
		if ($data instanceof QueryBuilder) {
			return new DbalCollection($this->getRepository(), $this->connection, $data);

		} elseif (is_array($data)) {
			$result = array_map([$this->getRepository(), 'hydrateEntity'], $data);
			return new ArrayCollection($result, $this->getRepository());

		} elseif ($data instanceof Result) {
			$result = [];
			$repository = $this->getRepository();
			foreach ($data as $row) {
				$result[] = $repository->hydrateEntity($row->toArray());
			}
			return new ArrayCollection($result, $this->getRepository());
		}

		throw new InvalidArgumentException('DbalMapper can convert only array|QueryBuilder|Result to ICollection.');
	}


	public function getManyHasManyParameters(PropertyMetadata $sourceProperty, IMapper $targetMapper)
	{
		return [
			$this->getStorageReflection()->getManyHasManyStorageName($targetMapper),
			$this->getStorageReflection()->getManyHasManyStoragePrimaryKeys($targetMapper),
		];
	}


	// == Relationship mappers =========================================================================================


	public function createCollectionManyHasOne(PropertyMetadata $metadata, IEntity $parent)
	{
		return $this
			->findAll()
			->setRelationshipMapping(
				$this->getRelationshipMapperManyHasOne($metadata),
				$parent
			);
	}


	public function createCollectionOneHasOne(PropertyMetadata $metadata, IEntity $parent)
	{
		return $this
			->findAll()
			->setRelationshipMapping(
				$metadata->relationship->isMain
					? $this->getRelationshipMapperManyHasOne($metadata)
					: $this->getRelationshipMapperOneHasOne($metadata),
				$parent
			);
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->relationship->isMain ? $mapperTwo : $this;
		return $targetMapper
			->findAll()
			->setRelationshipMapping(
				$this->getRelationshipMapperManyHasMany($mapperTwo, $metadata),
				$parent
			);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent)
	{
		return $this
			->findAll()
			->setRelationshipMapping(
				$this->getRelationshipMapperOneHasMany($metadata),
				$parent
			);
	}


	public function getRelationshipMapperManyHasOne(PropertyMetadata $metadata)
	{
		$key = spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[0][$key])) {
			$this->cacheRM[0][$key] = $this->createRelationshipMapperManyHasOne($metadata);
		}

		return $this->cacheRM[0][$key];
	}


	public function getRelationshipMapperOneHasOne($metadata)
	{
		$key = spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[1][$key])) {
			$this->cacheRM[1][$key] = $this->createRelationshipMapperOneHasOne($metadata);
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


	protected function createRelationshipMapperManyHasOne(PropertyMetadata $metadata)
	{
		return new RelationshipMapperManyHasOne($this->connection, $this, $metadata);
	}


	protected function createRelationshipMapperOneHasOne($metadata)
	{
		return new RelationshipMapperOneHasOne($this->connection, $this, $metadata);
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
			$this->getRepository()->getEntityMetadata()->getPrimaryKey(),
			$this->cacheStorage
		);
	}


	// == Persistence API ==============================================================================================


	public function persist(IEntity $entity)
	{
		$this->beginTransaction();
		$data = $this->entityToArray($entity);
		$data = $this->getStorageReflection()->convertEntityToStorage($data);

		if (!$entity->isPersisted()) {
			$this->connection->query('INSERT INTO %table %values', $this->getTableName(), $data);
			return $entity->hasValue('id')
				? $entity->getValue('id')
				: $this->connection->getLastInsertedId($this->getStorageReflection()->getPrimarySequenceName());

		} else {
			$primary = [];
			$id = (array) $entity->getPersistedId();
			foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}

			$this->connection->query('UPDATE %table SET %set WHERE %and', $this->getTableName(), $data, $primary);
			return $entity->getPersistedId();
		}
	}


	public function remove(IEntity $entity)
	{
		$this->beginTransaction();

		$primary = [];
		$id = (array) $entity->getPersistedId();
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
			} elseif ($metadataProperty->isPrimary && ($entity->isPersisted() || !$entity->hasValue($name))) {
				continue;
			}

			if ($metadataProperty->relationship !== NULL) {
				$rel = $metadataProperty->relationship;
				$canSkip = $rel->type === PropertyRelationshipMetadata::ONE_HAS_MANY
					|| $rel->type === PropertyRelationshipMetadata::MANY_HAS_MANY
					|| ($rel->type === PropertyRelationshipMetadata::ONE_HAS_ONE && !$rel->isMain);
				if ($canSkip) {
					continue;
				}
			}

			$property = $entity->getProperty($name);
			if ($property instanceof IProperty) {
				$value = $property->getRawValue();

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
			$this->connection->beginTransaction();
			self::$transactions[$hash] = TRUE;
		}
	}


	public function flush()
	{
		parent::flush();
		$this->cacheRM = [];
		$hash = spl_object_hash($this->connection);
		if (isset(self::$transactions[$hash])) {
			$this->connection->commitTransaction();
			unset(self::$transactions[$hash]);
		}
	}


	public function rollback()
	{
		$hash = spl_object_hash($this->connection);
		if (isset(self::$transactions[$hash])) {
			$this->connection->rollbackTransaction();
			unset(self::$transactions[$hash]);
		}
	}
}

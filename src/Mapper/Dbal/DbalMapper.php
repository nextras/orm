<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Caching\Cache;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Platforms\PostgreSqlPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;


class DbalMapper extends BaseMapper
{
	/** @var Connection */
	protected $connection;

	/** @var Cache */
	protected $cache;

	/** @var array */
	private $cacheRM = [];

	/** @var array */
	private static $transactions = [];


	public function __construct(Connection $connection, Cache $cache)
	{
		$key = md5(json_encode($connection->getConfig()));
		$this->connection = $connection;
		$this->cache = $cache->derive('mapper.' . $key);
	}


	/** @inheritdoc */
	public function findAll()
	{
		return new DbalCollection($this->getRepository(), $this->connection, $this->builder());
	}


	/**
	 * @return QueryBuilder
	 */
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


	/** @inheritdoc */
	public function clearCollectionCache()
	{
		parent::clearCollectionCache();
		$this->cacheRM = [];
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
		return $this->findAll()->setRelationshipMapping(
			$this->getRelationshipMapper(Relationship::MANY_HAS_ONE, $metadata),
			$parent
		);
	}


	public function createCollectionOneHasOne(PropertyMetadata $metadata, IEntity $parent)
	{
		return $this->findAll()->setRelationshipMapping(
			$metadata->relationship->isMain
				? $this->getRelationshipMapper(Relationship::MANY_HAS_ONE, $metadata)
				: $this->getRelationshipMapper(Relationship::ONE_HAS_ONE, $metadata),
			$parent
		);
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->relationship->isMain ? $mapperTwo : $this;
		return $targetMapper->findAll()->setRelationshipMapping(
			$this->getRelationshipMapper(Relationship::MANY_HAS_MANY, $metadata, $mapperTwo),
			$parent
		);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent)
	{
		return $this->findAll()->setRelationshipMapping(
			$this->getRelationshipMapper(Relationship::ONE_HAS_MANY, $metadata),
			$parent
		);
	}


	protected function getRelationshipMapper($type, PropertyMetadata $metadata, IMapper $otherMapper = null)
	{
		$key = $type . spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[$key])) {
			$this->cacheRM[$key] = $this->createRelationshipMapper($type, $metadata, $otherMapper);
		}
		return $this->cacheRM[$key];
	}


	protected function createRelationshipMapper($type, PropertyMetadata $metadata, IMapper $otherMapper = null)
	{
		switch ($type) {
			case Relationship::MANY_HAS_ONE:
				return new RelationshipMapperManyHasOne($this->connection, $this, $metadata);
			case Relationship::ONE_HAS_ONE:
				return new RelationshipMapperOneHasOne($this->connection, $this, $metadata);
			case Relationship::MANY_HAS_MANY:
				return new RelationshipMapperManyHasMany($this->connection, $this, $otherMapper, $metadata);
			case Relationship::ONE_HAS_MANY:
				return new RelationshipMapperOneHasMany($this->connection, $this, $metadata);
			default:
				throw new InvalidArgumentException();
		}
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
			$this->cache
		);
	}


	// == Persistence API ==============================================================================================


	public function persist(IEntity $entity)
	{
		$this->beginTransaction();
		$data = $this->entityToArray($entity);
		$data = $this->getStorageReflection()->convertEntityToStorage($data);

		if (!$entity->isPersisted()) {
			$this->processInsert($entity, $data);
			return $entity->hasValue('id')
				? $entity->getValue('id')
				: $this->connection->getLastInsertedId($this->getStorageReflection()->getPrimarySequenceName());

		} else {
			$primary = [];
			$id = (array) $entity->getPersistedId();
			foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}

			$this->processUpdate($entity, $data, $primary);
			return $entity->getPersistedId();
		}
	}


	protected function processInsert(IEntity $entity, $data)
	{
		$args = ['INSERT INTO %table %values', $this->getTableName(), $data];
		if ($this instanceof IPersistAutoupdateMapper) {
			$this->processAutoupdate($entity, $args);
		} else {
			$this->connection->queryArgs($args);
		}
	}


	protected function processUpdate(IEntity $entity, $data, $primary)
	{
		if (empty($data)) {
			return;
		}

		$args = ['UPDATE %table SET %set WHERE %and', $this->getTableName(), $data, $primary];
		if ($this instanceof IPersistAutoupdateMapper) {
			$this->processAutoupdate($entity, $args);
		} else {
			$this->connection->queryArgs($args);
		}
	}


	public function getAutoupdateReselectExpression()
	{
		return ['%column[]', ['*']];
	}


	protected function processAutoupdate(IEntity $entity, array $args)
	{
		$platform = $this->connection->getPlatform();
		if ($platform instanceof PostgreSqlPlatform) {
			$this->processPostgreAutoupdate($entity, $args);
		} else {
			$this->processMySQLAutoupdate($entity, $args);
		}
	}


	protected function processPostgreAutoupdate(IEntity $entity, array $args)
	{
		$args[] = 'RETURNING %ex';
		$args[] = $this->getAutoupdateReselectExpression();
		$row = $this->connection->queryArgs($args)->fetch();
		$data = $this->getStorageReflection()->convertStorageToEntity($row->toArray());
		$entity->fireEvent('onRefresh', [$data]);
	}


	protected function processMySQLAutoupdate(IEntity $entity, array $args)
	{
		$this->connection->queryArgs($args);

		$primary = [];
		$id = (array) ($entity->isPersisted() ? $entity->getPersistedId() : $this->connection->getLastInsertedId());
		foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
			$primary[$key] = array_shift($id);
		}

		$row = $this->connection->query(
			'SELECT %ex FROM %table WHERE %and',
			$this->getAutoupdateReselectExpression(),
			$this->getTableName(),
			$primary
		)->fetch();
		$data = $this->getStorageReflection()->convertStorageToEntity($row->toArray());
		$entity->fireEvent('onRefresh', [$data]);
	}


	public function remove(IEntity $entity)
	{
		$this->beginTransaction();

		$primary = [];
		$id = (array) $entity->getPersistedId();
		foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
			$primary[$key] = array_shift($id);
		}

		$this->processRemove($entity, $primary);
	}


	protected function processRemove(IEntity $entity, $primary)
	{
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
			} elseif ($entity->isPersisted() && !$entity->isModified($name)) {
				continue;
			}

			if ($metadataProperty->relationship !== null) {
				$rel = $metadataProperty->relationship;
				$canSkip
					= $rel->type === Relationship::ONE_HAS_MANY
					|| $rel->type === Relationship::MANY_HAS_MANY
					|| ($rel->type === Relationship::ONE_HAS_ONE && !$rel->isMain);
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
			self::$transactions[$hash] = true;
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

<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Caching\Cache;
use Nette\Utils\Json;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\SnakeCaseInflector;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Mapper\MapperRepositoryTrait;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\StorageReflection\StringHelper;


class DbalMapper implements IMapper
{
	use MapperRepositoryTrait;

	/** @var IConnection */
	protected $connection;

	/** @var string */
	protected $tableName;

	/** @var Cache */
	protected $cache;

	/** @var array */
	private $cacheRM = [];

	/** @var DbalMapperCoordinator */
	private $mapperCoordinator;

	/** @var IConventions */
	private $conventions;


	public function __construct(IConnection $connection, DbalMapperCoordinator $mapperCoordinator, Cache $cache)
	{
		$key = md5(Json::encode($connection->getConfig()));
		$this->connection = $connection;
		$this->mapperCoordinator = $mapperCoordinator;
		$this->cache = $cache->derive('mapper.' . $key);
	}


	/** {@inheritdoc} */
	public function findAll(): ICollection
	{
		return new DbalCollection($this, $this->connection, $this->builder());
	}


	public function builder(): QueryBuilder
	{
		$tableName = $this->getTableName();
		$alias = QueryBuilderHelper::getAlias($tableName);
		$builder = new QueryBuilder($this->connection->getDriver());
		$builder->from("[$tableName]", $alias);
		$builder->select("[$alias.*]");
		return $builder;
	}


	public function getDatabasePlatform(): IPlatform
	{
		return $this->connection->getPlatform();
	}


	public function getTableName(): string
	{
		if (!$this->tableName) {
			$className = preg_replace('~^.+\\\\~', '', get_class($this));
			assert($className !== null);
			$tableName = str_replace('Mapper', '', $className);
			$this->tableName = StringHelper::underscore($tableName);
		}

		return $this->tableName;
	}

	/**
	 * Transforms value from mapper, which is not a collection.
	 * @param QueryBuilder|array|Result $data
	 */
	public function toCollection($data): ICollection
	{
		if ($data instanceof QueryBuilder) {
			return new DbalCollection($this, $this->connection, $data);
		} elseif (is_array($data)) {
			$conventions = $this->getConventions();
			$result = array_map(
				[$this->getRepository(), 'hydrateEntity'],
				array_map([$conventions, 'convertStorageToEntity'], $data)
			);
			return new ArrayCollection($result, $this->getRepository());
		} elseif ($data instanceof Result) {
			$result = [];
			$repository = $this->getRepository();
			$conventions = $this->getConventions();
			foreach ($data as $row) {
				$result[] = $repository->hydrateEntity($conventions->convertStorageToEntity($row->toArray()));
			}
			return new ArrayCollection($result, $this->getRepository());
		}

		throw new InvalidArgumentException('DbalMapper can convert only array|QueryBuilder|Result to ICollection.');
	}


	/**
	 * @param QueryBuilder|Result|Row|array $data
	 */
	public function toEntity($data): ?IEntity
	{
		if ($data instanceof QueryBuilder) {
			$data = $this->connection->queryByQueryBuilder($data);
		}
		if ($data instanceof Result) {
			$data = $data->fetch();
			if ($data === null) {
				return null;
			}
		}
		if ($data instanceof Row) {
			$data = $data->toArray();
		}
		if (is_array($data)) {
			return $this->hydrateEntity($data);
		}

		throw new InvalidArgumentException('DbalMapper can convert only array|QueryBuilder|Result|Row to IEntity.');
	}


	public function hydrateEntity(array $data): ?IEntity
	{
		return $this->getRepository()->hydrateEntity($this->getConventions()->convertStorageToEntity($data));
	}


	/** {@inheritdoc} */
	public function clearCache()
	{
		$this->cacheRM = [];
	}


	public function getManyHasManyParameters(PropertyMetadata $sourceProperty, DbalMapper $targetMapper)
	{
		return [
			$this->getConventions()->getManyHasManyStorageName($targetMapper->getConventions()),
			$this->getConventions()->getManyHasManyStoragePrimaryKeys($targetMapper->getConventions()),
		];
	}


	// == Relationship mappers =========================================================================================

	public function createCollectionManyHasOne(PropertyMetadata $metadata): ICollection
	{
		return $this->findAll()->setRelationshipMapper(
			$this->getRelationshipMapper(Relationship::MANY_HAS_ONE, $metadata)
		);
	}


	public function createCollectionOneHasOne(PropertyMetadata $metadata): ICollection
	{
		assert($metadata->relationship !== null);
		return $this->findAll()->setRelationshipMapper(
			$metadata->relationship->isMain
				? $this->getRelationshipMapper(Relationship::MANY_HAS_ONE, $metadata)
				: $this->getRelationshipMapper(Relationship::ONE_HAS_ONE, $metadata)
		);
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata): ICollection
	{
		assert($metadata->relationship !== null);
		$targetMapper = $metadata->relationship->isMain ? $mapperTwo : $this;
		return $targetMapper->findAll()->setRelationshipMapper(
			$this->getRelationshipMapper(Relationship::MANY_HAS_MANY, $metadata, $mapperTwo)
		);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection
	{
		return $this->findAll()->setRelationshipMapper(
			$this->getRelationshipMapper(Relationship::ONE_HAS_MANY, $metadata)
		);
	}


	protected function getRelationshipMapper(
		$type,
		PropertyMetadata $metadata,
		IMapper $otherMapper = null
	): IRelationshipMapper
	{
		$key = $type . spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[$key])) {
			$this->cacheRM[$key] = $this->createRelationshipMapper($type, $metadata, $otherMapper);
		}
		return $this->cacheRM[$key];
	}


	protected function createRelationshipMapper(
		$type,
		PropertyMetadata $metadata,
		IMapper $otherMapper = null
	): IRelationshipMapper
	{
		switch ($type) {
			case Relationship::MANY_HAS_ONE:
				return new RelationshipMapperManyHasOne($this->connection, $this, $metadata);
			case Relationship::ONE_HAS_ONE:
				return new RelationshipMapperOneHasOne($this->connection, $this, $metadata);
			case Relationship::MANY_HAS_MANY:
				assert($otherMapper instanceof DbalMapper);
				return new RelationshipMapperManyHasMany($this->connection, $this, $otherMapper, $this->mapperCoordinator, $metadata);
			case Relationship::ONE_HAS_MANY:
				return new RelationshipMapperOneHasMany($this->connection, $this, $metadata);
			default:
				throw new InvalidArgumentException();
		}
	}


	public function getConventions(): IConventions
	{
		if ($this->conventions === null) {
			$this->conventions = $this->createConventions();
		}

		return $this->conventions;
	}


	protected function createConventions()
	{
		return new Conventions\Conventions(
			new SnakeCaseInflector(),
			$this->connection,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata(),
			$this->cache
		);
	}


	// == Persistence API ==============================================================================================


	/**
	 * @return int|array
	 */
	public function persist(IEntity $entity)
	{
		$this->beginTransaction();
		$data = $this->entityToArray($entity);
		$data = $this->getConventions()->convertEntityToStorage($data);

		if (!$entity->isPersisted()) {
			$this->processInsert($entity, $data);
			return $entity->hasValue('id')
				? $entity->getValue('id')
				: $this->connection->getLastInsertedId($this->getConventions()->getPrimarySequenceName());
		} else {
			$primary = [];
			$id = (array) $entity->getPersistedId();
			foreach ($entity->getMetadata()->getPrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}
			$primary = $this->getConventions()->convertEntityToStorage($primary);

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


	protected function processAutoupdate(IEntity $entity, array $args)
	{
		$platform = $this->connection->getPlatform()->getName();
		if ($platform === 'pgsql') {
			$this->processPostgreAutoupdate($entity, $args);
		} elseif ($platform === 'mysql') {
			$this->processMySQLAutoupdate($entity, $args);
		} else {
			throw new NotSupportedException();
		}
	}


	protected function processPostgreAutoupdate(IEntity $entity, array $args)
	{
		assert($this instanceof IPersistAutoupdateMapper);
		$args[] = 'RETURNING %ex';
		$args[] = $this->getAutoupdateReselectExpression();
		$row = $this->connection->queryArgs($args)->fetch();
		if ($row === null) {
			$entity->onRefresh(null, true);
		} else {
			$data = $this->getConventions()->convertStorageToEntity($row->toArray());
			$entity->onRefresh($data, true);
		}
	}


	protected function processMySQLAutoupdate(IEntity $entity, array $args)
	{
		assert($this instanceof IPersistAutoupdateMapper);
		$this->connection->queryArgs($args);

		$conventions = $this->getConventions();

		$primary = [];
		$id = (array) ($entity->isPersisted() ? $entity->getPersistedId() : $this->connection->getLastInsertedId());
		foreach ($entity->getMetadata()->getPrimaryKey() as $key) {
			$key = $conventions->convertEntityToStorageKey($key);
			$primary[$key] = array_shift($id);
		}

		$row = $this->connection->query(
			'SELECT %ex FROM %table WHERE %and',
			$this->getAutoupdateReselectExpression(),
			$this->getTableName(),
			$primary
		)->fetch();

		if ($row === null) {
			$entity->onRefresh(null, true);
		} else {
			$data = $conventions->convertStorageToEntity($row->toArray());
			$entity->onRefresh($data, true);
		}
	}


	public function remove(IEntity $entity)
	{
		$this->beginTransaction();
		$conventions = $this->getConventions();

		$primary = [];
		$id = (array) $entity->getPersistedId();
		foreach ($entity->getMetadata()->getPrimaryKey() as $key) {
			$key = $conventions->convertEntityToStorageKey($key);
			$primary[$key] = array_shift($id);
		}

		$this->processRemove($entity, $primary);
	}


	protected function processRemove(IEntity $entity, $primary)
	{
		$this->connection->query('DELETE FROM %table WHERE %and', $this->getTableName(), $primary);
	}


	protected function entityToArray(IEntity $entity): array
	{
		return $entity->getRawValues(/* $modifiedOnly = */ true);
	}


	// == Transactions API =============================================================================================

	public function beginTransaction()
	{
		$this->mapperCoordinator->beginTransaction();
	}


	public function flush(): void
	{
		$this->cacheRM = [];
		$this->mapperCoordinator->flush();
	}


	public function rollback()
	{
		$this->mapperCoordinator->rollback();
	}
}

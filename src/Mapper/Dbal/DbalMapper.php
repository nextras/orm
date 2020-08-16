<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use Nette\Caching\Cache;
use Nette\Utils\Json;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\IInflector;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\SnakeCaseInflector;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Mapper\MapperRepositoryTrait;
use Nextras\Orm\StorageReflection\StringHelper;


class DbalMapper implements IMapper
{
	use MapperRepositoryTrait;


	/** @var IConnection */
	protected $connection;

	/** @var string|null */
	protected $tableName;

	/** @var Cache */
	protected $cache;

	/**
	 * @var IRelationshipMapper[]
	 * @phpstan-var array<string, IRelationshipMapper>
	 */
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
		$this->cache = $cache->derive('orm.mapper.' . $key);
	}


	/** {@inheritdoc} */
	public function findAll(): ICollection
	{
		return new DbalCollection($this, $this->connection, $this->builder());
	}


	public function builder(): QueryBuilder
	{
		$tableName = $this->getTableName();
		$alias = DbalQueryBuilderHelper::getAlias($tableName);
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
		if ($this->tableName === null) {
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
	 * @phpstan-param QueryBuilder|list<array<string, mixed>>|Result $data
	 */
	public function toCollection($data): ICollection
	{
		if ($data instanceof QueryBuilder) {
			return new DbalCollection($this, $this->connection, $data);
		}

		$repository = $this->getRepository();
		$conventions = $this->getConventions();

		if (is_array($data)) {
			$result = array_values(array_filter(array_map(
				[$repository, 'hydrateEntity'],
				array_map([$conventions, 'convertStorageToEntity'], $data)
			)));
			return new ArrayCollection($result, $repository);

		} elseif ($data instanceof Result) {
			$result = [];
			foreach ($data as $row) {
				$entity = $repository->hydrateEntity($conventions->convertStorageToEntity($row->toArray()));
				if ($entity !== null) {
					$result[] = $entity;
				}
			}
			return new ArrayCollection($result, $repository);
		}

		throw new InvalidArgumentException('DbalMapper can convert only array|QueryBuilder|Result to ICollection.');
	}


	/**
	 * @param QueryBuilder|Result|Row|array $data
	 * @phpstan-param QueryBuilder|Result|Row|array<string, mixed> $data
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


	/**
	 * @param array<string, mixed> $data
	 */
	public function hydrateEntity(array $data): ?IEntity
	{
		return $this->getRepository()->hydrateEntity($this->getConventions()->convertStorageToEntity($data));
	}


	/** {@inheritdoc} */
	public function clearCache(): void
	{
		$this->cacheRM = [];
	}


	/**
	 * @phpstan-return array{string,array{string,string}}
	 */
	public function getManyHasManyParameters(PropertyMetadata $sourceProperty, DbalMapper $targetMapper): array
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


	public function createCollectionManyHasMany(IMapper $sourceMapper, PropertyMetadata $metadata): ICollection
	{
		return $this->findAll()->setRelationshipMapper(
			$this->getRelationshipMapper(Relationship::MANY_HAS_MANY, $metadata, $sourceMapper)
		);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection
	{
		return $this->findAll()->setRelationshipMapper(
			$this->getRelationshipMapper(Relationship::ONE_HAS_MANY, $metadata)
		);
	}


	protected function getRelationshipMapper(
		int $type,
		PropertyMetadata $metadata,
		?IMapper $sourceMapper = null
	): IRelationshipMapper
	{
		$key = $type . spl_object_hash($metadata) . $metadata->name;
		if (!isset($this->cacheRM[$key])) {
			$this->cacheRM[$key] = $this->createRelationshipMapper($type, $metadata, $sourceMapper);
		}
		return $this->cacheRM[$key];
	}


	protected function createRelationshipMapper(
		int $type,
		PropertyMetadata $metadata,
		?IMapper $sourceMapper = null
	): IRelationshipMapper
	{
		switch ($type) {
			case Relationship::MANY_HAS_ONE:
				return new RelationshipMapperManyHasOne($this->connection, $this, $metadata);
			case Relationship::ONE_HAS_ONE:
				return new RelationshipMapperOneHasOne($this->connection, $this, $metadata);
			case Relationship::MANY_HAS_MANY:
				assert($sourceMapper instanceof DbalMapper);
				return new RelationshipMapperManyHasMany($this->connection, $this, $sourceMapper, $this->mapperCoordinator, $metadata);
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


	protected function createConventions(): IConventions
	{
		return new Conventions\Conventions(
			$this->createInflector(),
			$this->connection,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata(),
			$this->cache
		);
	}


	protected function createInflector(): IInflector
	{
		return new SnakeCaseInflector();
	}


	// == Persistence API ==============================================================================================

	public function persist(IEntity $entity): void
	{
		$this->beginTransaction();
		$data = $this->entityToArray($entity);
		$data = $this->getConventions()->convertEntityToStorage($data);

		if (!$entity->isPersisted()) {
			$this->processInsert($entity, $data);

		} else {
			$primary = [];
			$id = (array) $entity->getPersistedId();
			foreach ($entity->getMetadata()->getPrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}
			$primary = $this->getConventions()->convertEntityToStorage($primary);

			$this->processUpdate($entity, $data, $primary);
		}
	}


	/**
	 * @phpstan-param array<string, mixed> $data
	 */
	protected function processInsert(IEntity $entity, array $data): void
	{
		$args = ['INSERT INTO %table %values', $this->getTableName(), $data];
		if ($this instanceof IPersistAutoupdateMapper) {
			$this->processAutoupdate($entity, $args);
		} else {
			$this->connection->queryArgs($args);

			$id = $entity->hasValue('id')
				? $entity->getValue('id')
				: $this->connection->getLastInsertedId($this->getConventions()->getPrimarySequenceName());
			$entity->onPersist($id);
		}
	}


	/**
	 * @phpstan-param array<string, mixed> $data
	 * @phpstan-param array<string, mixed> $primary
	 */
	protected function processUpdate(IEntity $entity, array $data, array $primary): void
	{
		if (count($data) === 0) {
			return;
		}

		$args = ['UPDATE %table SET %set WHERE %and', $this->getTableName(), $data, $primary];
		if ($this instanceof IPersistAutoupdateMapper) {
			$this->processAutoupdate($entity, $args);
		} else {
			$this->connection->queryArgs($args);
			$entity->onPersist($entity->getPersistedId());
		}
	}


	/**
	 * @phpstan-param list<mixed> $args
	 */
	protected function processAutoupdate(IEntity $entity, array $args): void
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


	/**
	 * @phpstan-param list<mixed> $args
	 */
	protected function processPostgreAutoupdate(IEntity $entity, array $args): void
	{
		assert($this instanceof IPersistAutoupdateMapper);
		$args[] = 'RETURNING %ex';
		$args[] = $this->getAutoupdateReselectExpression();
		$row = $this->connection->queryArgs($args)->fetch();

		$id = $entity->hasValue('id')
			? $entity->getValue('id')
			: $this->connection->getLastInsertedId($this->getConventions()->getPrimarySequenceName());
		$entity->onPersist($id);

		if ($row === null) {
			$entity->onRefresh(null, true);
		} else {
			$data = $this->getConventions()->convertStorageToEntity($row->toArray());
			$entity->onRefresh($data, true);
		}
	}


	/**
	 * @phpstan-param list<mixed> $args
	 */
	protected function processMySQLAutoupdate(IEntity $entity, array $args): void
	{
		assert($this instanceof IPersistAutoupdateMapper);
		$this->connection->queryArgs($args);

		$id = $entity->hasValue('id')
			? $entity->getValue('id')
			: $this->connection->getLastInsertedId();
		$entity->onPersist($id);

		$conventions = $this->getConventions();

		$id = (array) $entity->getPersistedId();
		$primary = [];
		foreach ($entity->getMetadata()->getPrimaryKey() as $key) {
			$primary[$key] = array_shift($id);
		}
		$primary = $this->getConventions()->convertEntityToStorage($primary);

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


	public function remove(IEntity $entity): void
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


	/**
	 * @param array<string, mixed> $primary
	 */
	protected function processRemove(IEntity $entity, array $primary): void
	{
		$this->connection->query('DELETE FROM %table WHERE %and', $this->getTableName(), $primary);
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function entityToArray(IEntity $entity): array
	{
		return $entity->getRawValues(/* $modifiedOnly = */ true);
	}


	// == Transactions API =============================================================================================

	public function flush(): void
	{
		$this->cacheRM = [];
		$this->mapperCoordinator->flush();
	}


	public function beginTransaction(): void
	{
		$this->mapperCoordinator->beginTransaction();
	}


	public function rollback(): void
	{
		$this->mapperCoordinator->rollback();
	}
}

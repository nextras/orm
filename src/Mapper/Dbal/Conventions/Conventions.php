<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions;


use Nette\Caching\Cache;
use Nette\SmartObject;
use Nette\Utils\Arrays;
use Nextras\Dbal\Bridges\NetteCaching\CachedPlatform;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\IInflector;
use function array_map;
use function array_shift;
use function assert;
use function count;
use function explode;
use function implode;
use function md5;
use function sprintf;
use function stripos;


class Conventions implements IConventions
{
	use SmartObject;


	public const TO_STORAGE = 0;
	public const TO_ENTITY = 1;
	public const TO_STORAGE_FLATTENING = 2;
	private const NOT_FOUND = "\0";

	public string $manyHasManyStorageNamePattern = '%s_x_%s';
	public string $embeddableSeparatorPattern = '_';

	protected CachedPlatform $platform;
	protected bool $storageNameWithSchema;
	protected Table $storageTable;

	/**
	 * @var array{
	 *      array<string, array{string, 1?: (callable(mixed $value, string $newKey): mixed)|null}>,
	 *      array<string, array{string, 1?: (callable(mixed $value, string $newKey): mixed)|null}>,
	 *      array<string, array<string>>,
	 * }
	 */
	protected array $mappings;

	/** @var array<string, literal-string> */
	protected array $modifiers;

	/** @var list<string> */
	protected array $storagePrimaryKey = [];


	/**
	 * @param literal-string|Fqn $storageName
	 */
	public function __construct(
		protected readonly IInflector $inflector,
		IConnection $connection,
		string|Fqn $storageName,
		protected readonly EntityMetadata $entityMetadata,
		Cache $cache,
	)
	{
		$this->platform = new CachedPlatform($connection->getPlatform(), $cache->derive('orm.db_reflection'));
		$this->storageNameWithSchema = $storageName instanceof Fqn;
		$this->storageTable = $this->findStorageTable($storageName);

		$cache = $cache->derive('orm.storage_reflection');
		$this->mappings = $cache->load(
			'nextras.orm.storage_reflection.' . md5($this->storageTable->fqnName->getUnescaped()) . '.mappings',
			function (): array {
				return $this->getDefaultMappings();
			},
		);
		$this->modifiers = $cache->load(
			'nextras.orm.storage_reflection.' . md5($this->storageTable->fqnName->getUnescaped()) . '.modifiers',
			function (): array {
				return $this->getDefaultModifiers();
			},
		);
	}


	public function getStorageTable(): Table
	{
		return $this->storageTable;
	}


	/**
	 * @param literal-string|Fqn $tableName
	 */
	private function findStorageTable(string|Fqn $tableName): Table
	{
		if ($tableName instanceof Fqn) {
			$schema = $tableName->schema;
			$tableName = $tableName->name;
		} else {
			$schema = null;
		}

		$tables = $this->platform->getTables($schema);
		foreach ($tables as $table) {
			if ($table->fqnName->name === $tableName) {
				return $table;
			}
		}

		$schema = $schema !== null ? "$schema." : '';
		throw new InvalidStateException("Cannot find '$schema$tableName' table.");
	}


	public function getStoragePrimaryKey(): array
	{
		if (count($this->storagePrimaryKey) === 0) {
			$primaryKeys = [];
			$columns = $this->platform->getColumns(
				table: $this->storageTable->fqnName->name,
				schema: $this->storageTable->fqnName->schema,
			);
			foreach ($columns as $column => $meta) {
				if ($meta->isPrimary) {
					$primaryKeys[] = $column;
				}
			}
			if (count($primaryKeys) === 0) {
				throw new InvalidArgumentException("Table '{$this->storageTable->fqnName->getUnescaped()}' has not defined any primary key.");
			}
			$this->storagePrimaryKey = $primaryKeys;
		}

		return $this->storagePrimaryKey;
	}


	public function convertEntityToStorage(array $in): array
	{
		$out = [];

		foreach ($this->mappings[self::TO_STORAGE_FLATTENING] as $to => $from) {
			$value = Arrays::get($in, $from, self::NOT_FOUND);
			if ($value !== self::NOT_FOUND) {
				$in[$to] = $value;
			}
		}
		foreach ($this->mappings[self::TO_STORAGE_FLATTENING] as $from) {
			unset($in[$from[0]]);
		}

		foreach ($in as $key => $val) {
			if (isset($this->mappings[self::TO_STORAGE][$key][0])) {
				$newKey = $this->mappings[self::TO_STORAGE][$key][0];
			} else {
				$newKey = $this->convertEntityToStorageKey($key);
			}

			if (isset($this->modifiers[$newKey])) {
				$newKey .= $this->modifiers[$newKey];
			}

			if (isset($this->mappings[self::TO_STORAGE][$key][1])) {
				$converter = $this->mappings[self::TO_STORAGE][$key][1];
				$out[$newKey] = $converter($val, $newKey);
			} else {
				$out[$newKey] = $val;
			}
		}

		return $out;
	}


	public function convertStorageToEntity(array $in): array
	{
		$out = [];

		foreach ($in as $key => $val) {
			if (isset($this->mappings[self::TO_ENTITY][$key][0])) {
				$newKey = $this->mappings[self::TO_ENTITY][$key][0];
			} else {
				$newKey = $this->convertStorageToEntityKey((string) $key);
			}

			if (isset($this->mappings[self::TO_ENTITY][$key][1])) {
				$converter = $this->mappings[self::TO_ENTITY][$key][1];
				$val = $converter($val, $newKey);
			}

			if (stripos($newKey, '->') !== false) {
				$ref = &Arrays::getRef($out, explode('->', $newKey)); // @phpstan-ignore-line
				$ref = $val;
			} else {
				$out[$newKey] = $val;
			}
		}

		return $out;
	}


	public function convertStorageToEntityKey(string $key): string
	{
		if (!isset($this->mappings[self::TO_ENTITY][$key][0])) {
			$this->mappings[self::TO_ENTITY][$key] = [$this->inflector->formatAsProperty($key)];
		}

		return $this->mappings[self::TO_ENTITY][$key][0];
	}


	public function convertEntityToStorageKey(string $key): string
	{
		if (!isset($this->mappings[self::TO_STORAGE][$key][0])) {
			$this->mappings[self::TO_STORAGE][$key] = [$this->inflector->formatAsColumn($key)];
		}

		return $this->mappings[self::TO_STORAGE][$key][0];
	}


	public function getPrimarySequenceName(): ?string
	{
		return $this->platform->getPrimarySequenceName(
			table: $this->storageTable->fqnName->name,
			schema: $this->storageTable->fqnName->schema,
		);
	}


	public function getManyHasManyStorageName(IConventions $targetConventions): string|Fqn
	{
		$primary = $this->storageTable->fqnName->name;
		$secondary = $targetConventions->getStorageTable()->fqnName->name;
		$table = sprintf($this->manyHasManyStorageNamePattern, $primary, $secondary);

		if ($this->storageNameWithSchema) {
			$schema = $this->storageTable->fqnName->schema;
			return new Fqn(schema: $schema, name: $table);
		} else {
			return $table;
		}
	}


	public function getManyHasManyStoragePrimaryKeys(IConventions $targetConventions): array
	{
		return $this->findManyHasManyPrimaryColumns(
			$this->getManyHasManyStorageName($targetConventions),
			$targetConventions->getStorageTable(),
		);
	}


	public function addMapping(
		string $entity,
		string $storage,
		?callable $toEntityCb = null,
		?callable $toStorageCb = null,
	): IConventions
	{
		if (isset($this->mappings[self::TO_ENTITY][$storage])) {
			throw new InvalidStateException("Mapping for $storage column is already defined.");
		} elseif (isset($this->mappings[self::TO_STORAGE][$entity])) {
			throw new InvalidStateException("Mapping for $entity property is already defined.");
		}

		$this->mappings[self::TO_ENTITY][$storage] = [$entity, $toEntityCb];
		$this->mappings[self::TO_STORAGE][$entity] = [$storage, $toStorageCb];
		return $this;
	}


	public function setMapping(
		string $entity,
		string $storage,
		?callable $toEntityCb = null,
		?callable $toStorageCb = null,
	): IConventions
	{
		unset($this->mappings[self::TO_ENTITY][$storage], $this->mappings[self::TO_STORAGE][$entity]);
		return $this->addMapping($entity, $storage, $toEntityCb, $toStorageCb);
	}


	public function setModifier(string $storageKey, string $saveModifier): IConventions
	{
		$this->modifiers[$storageKey] = $saveModifier;
		return $this;
	}


	public function getModifier(string $storageKey): ?string
	{
		return $this->modifiers[$storageKey] ?? null;
	}


	/**
	 * @param string|Fqn $joinTable
	 * @return array{string,string}
	 */
	protected function findManyHasManyPrimaryColumns(string|Fqn $joinTable, Table $targetTable): array
	{
		$sourceTable = $this->storageTable;
		$sourceId = null;
		$targetId = null;

		$isCaseSensitive = $this->platform->getName() !== MySqlPlatform::NAME;

		if ($joinTable instanceof Fqn) {
			$foreignKeys = $this->platform->getForeignKeys(table: $joinTable->name, schema: $joinTable->schema);
		} else {
			$foreignKeys = $this->platform->getForeignKeys(table: $joinTable);
		}
		foreach ($foreignKeys as $column => $foreignKey) {
			$refTable = $foreignKey->refTable->getUnescaped();
			if ($isCaseSensitive) {
				if ($refTable === $sourceTable->fqnName->getUnescaped() && $sourceId === null) {
					$sourceId = $column;
				} elseif ($refTable === $targetTable->fqnName->getUnescaped()) {
					$targetId = $column;
				}
			} else {
				if (strcasecmp($refTable, $sourceTable->fqnName->getUnescaped()) === 0 && $sourceId === null) {
					$sourceId = $column;
				} elseif (strcasecmp($refTable, $targetTable->fqnName->getUnescaped()) === 0) {
					$targetId = $column;
				}
			}
		}

		if ($sourceId === null || $targetId === null) {
			$joinTable = $joinTable instanceof Fqn ? $joinTable->getUnescaped() : $joinTable;
			throw new InvalidStateException("No primary keys detected for many has many '$joinTable' join table.");
		}

		return [$sourceId, $targetId];
	}


	/**
	 * @return array{
	 *      array<string, array{string, 1?: callable|null}>,
	 *      array<string, array{string, 1?: callable|null}>,
	 *      array<string, list<string>>,
	 * }
	 */
	protected function getDefaultMappings(): array
	{
		$entityPrimaryKey = $this->entityMetadata->getPrimaryKey();
		$mappings = [
			self::TO_STORAGE => [],
			self::TO_ENTITY => [],
			self::TO_STORAGE_FLATTENING => [],
		];

		$foreignKeys = $this->platform->getForeignKeys(
			table: $this->storageTable->fqnName->name,
			schema: $this->storageTable->fqnName->schema,
		);
		foreach ($foreignKeys as $foreignKey) {
			$storageKey = $foreignKey->column;
			$entityKey = $this->inflector->formatAsRelationshipProperty($storageKey);
			$mappings[self::TO_ENTITY][$storageKey] = [$entityKey];
			$mappings[self::TO_STORAGE][$entityKey] = [$storageKey];
		}

		/** @var list<array{EntityMetadata, list<string>}> $toProcess */
		$toProcess = [[$this->entityMetadata, []]];
		while (($entry = array_shift($toProcess)) !== null) {
			[$metadata, $tokens] = $entry;
			foreach ($metadata->getProperties() as $property) {
				if ($property->wrapper !== EmbeddableContainer::class) {
					continue;
				}

				$subMetadata = $property->args[EmbeddableContainer::class]['metadata'];
				assert($subMetadata instanceof EntityMetadata);

				$baseTokens = $tokens;
				$baseTokens[] = $property->name;

				foreach ($subMetadata->getProperties() as $subProperty) {
					/** @var list<string> $propertyTokens */
					$propertyTokens = $baseTokens;
					$propertyTokens[] = $subProperty->name;

					$propertyKey = implode('->', $propertyTokens);
					$storageKey = implode(
						$this->embeddableSeparatorPattern,
						array_map(function ($key): string {
							return $this->inflector->formatAsColumn($key);
						}, $propertyTokens),
					);

					$mappings[self::TO_ENTITY][$storageKey] = [$propertyKey];
					$mappings[self::TO_STORAGE][$propertyKey] = [$storageKey];
					$mappings[self::TO_STORAGE_FLATTENING][$propertyKey] = $propertyTokens;

					if ($subProperty->wrapper === EmbeddableContainer::class) {
						assert($subProperty->args !== null);
						$toProcess[] = [
							$subProperty->args[EmbeddableContainer::class]['metadata'],
							$baseTokens,
						];
					}
				}
			}
		}

		$storagePrimaryKey = $this->getStoragePrimaryKey();
		if (count($entityPrimaryKey) !== count($storagePrimaryKey)) {
			throw new InvalidStateException(
				'Mismatch count of entity primary key (' . implode(', ', $entityPrimaryKey)
				. ') with storage primary key (' . implode(', ', $storagePrimaryKey) . ').'
			);
		}

		if (count($storagePrimaryKey) === 1) {
			$entityKey = $entityPrimaryKey[0];
			$storageKey = $storagePrimaryKey[0];
			$mappings[self::TO_ENTITY][$storageKey] = [$entityKey, null];
			$mappings[self::TO_STORAGE][$entityKey] = [$storageKey, null];
		}

		return $mappings;
	}


	/**
	 * @return array<string, string>
	 */
	protected function getDefaultModifiers(): array
	{
		$modifiers = [];
		$types = match ($this->platform->getName()) {
			'pgsql', 'mssql' => [
				'TIMESTAMP' => true,
				'DATE' => true,
			],
			'mysql' => [
				'DATETIME' => true,
				'DATE' => true,
			],
			default => throw new NotSupportedException(),
		};

		$columns = $this->platform->getColumns(
			table: $this->storageTable->fqnName->name,
			schema: $this->storageTable->fqnName->schema,
		);
		foreach ($columns as $column) {
			if (isset($types[$column->type])) {
				$modifiers[$column->name] = '%?ldt';
			}
		}

		return $modifiers;
	}
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions;


use Nette\Caching\Cache;
use Nette\SmartObject;
use Nette\Utils\Arrays;
use Nextras\Dbal\Bridges\NetteCaching\CachedPlatform;
use Nextras\Dbal\IConnection;
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
use function strpos;


class Conventions implements IConventions
{
	use SmartObject;


	const TO_STORAGE = 0;
	const TO_ENTITY = 1;
	const TO_STORAGE_FLATTENING = 2;
	private const NOT_FOUND = "\0";

	/** @var string */
	public $manyHasManyStorageNamePattern = '%s_x_%s';

	/** @var string */
	public $embeddableSeparatorPattern = '_';

	/** @var IInflector */
	protected $inflector;

	/** @var string */
	protected $storageName;

	/** @var bool */
	protected $storageNameWithSchema;

	/** @var Table */
	protected $storageTable;

	/** @var EntityMetadata */
	protected $entityMetadata;

	/**
	 * @var array
	 * @phpstan-var array{
	 *      array<string, array{string, 1?: (callable(mixed $value, string $newKey): mixed)|null}>,
	 *      array<string, array{string, 1?: (callable(mixed $value, string $newKey): mixed)|null}>,
	 *      array<string, array<string>>,
	 * }
	 */
	protected $mappings;

	/** @var array<string, string> */
	protected $modifiers;

	/**
	 * @var array
	 * @phpstan-var list<string>
	 */
	protected $storagePrimaryKey = [];

	/** @var CachedPlatform */
	protected $platform;


	public function __construct(
		IInflector $inflector,
		IConnection $connection,
		string $storageName,
		EntityMetadata $entityMetadata,
		Cache $cache
	)
	{
		$this->inflector = $inflector;
		$this->platform = new CachedPlatform($connection->getPlatform(), $cache->derive('orm.db_reflection'));
		$this->entityMetadata = $entityMetadata;
		$this->storageName = $storageName;
		$this->storageNameWithSchema = strpos($storageName, '.') !== false;
		$this->storageTable = $this->findStorageTable($this->storageName);

		$cache = $cache->derive('orm.storage_reflection');
		$this->mappings = $cache->load(
			'nextras.orm.storage_reflection.' . md5($this->storageName) . '.mappings',
			function (): array {
				return $this->getDefaultMappings();
			}
		);
		$this->modifiers = $cache->load(
			'nextras.orm.storage_reflection.' . md5($this->storageName) . '.modifiers',
			function (): array {
				return $this->getDefaultModifiers();
			}
		);
	}


	public function getStorageTable(): Table
	{
		return $this->storageTable;
	}


	private function findStorageTable(string $tableName): Table
	{
		if ($this->storageNameWithSchema) {
			[$schema, $tableName] = explode('.', $tableName);
		} else {
			$schema = null;
		}

		$tables = $this->platform->getTables($schema);
		foreach ($tables as $table) {
			if ($table->name === $tableName) {
				return $table;
			}
		}

		throw new InvalidStateException("Cannot find '$tableName' table reflection.");
	}


	public function getStoragePrimaryKey(): array
	{
		if (count($this->storagePrimaryKey) === 0) {
			$primaryKeys = [];
			foreach ($this->platform->getColumns($this->storageTable->getNameFqn()) as $column => $meta) {
				if ($meta->isPrimary) {
					$primaryKeys[] = $column;
				}
			}
			if (count($primaryKeys) === 0) {
				throw new InvalidArgumentException("Table '$this->storageName' has not defined any primary key.");
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
		return $this->platform->getPrimarySequenceName($this->storageTable->getNameFqn());
	}


	public function getManyHasManyStorageName(IConventions $targetConventions): string
	{
		$primary = $this->storageTable->name;
		$secondary = $targetConventions->getStorageTable()->name;
		$table = sprintf($this->manyHasManyStorageNamePattern, $primary, $secondary);

		if ($this->storageNameWithSchema) {
			$schema = $this->storageTable->schema;
			return "$schema.$table";
		} else {
			return $table;
		}
	}


	public function getManyHasManyStoragePrimaryKeys(IConventions $targetConventions): array
	{
		return $this->findManyHasManyPrimaryColumns(
			$this->getManyHasManyStorageName($targetConventions),
			$targetConventions->getStorageTable()
		);
	}


	public function addMapping(
		string $entity,
		string $storage,
		?callable $toEntityCb = null,
		?callable $toStorageCb = null
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
		?callable $toStorageCb = null
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


	/**
	 * @phpstan-return array{string,string}
	 */
	protected function findManyHasManyPrimaryColumns(string $joinTable, Table $targetTableReflection): array
	{
		$sourceTable = $this->storageTable->getNameFqn();
		$targetTable = $targetTableReflection->getNameFqn();
		$sourceId = $targetId = null;

		$isCaseSensitive = $this->platform->getName() !== MySqlPlatform::NAME;

		$keys = $this->platform->getForeignKeys($joinTable);
		foreach ($keys as $column => $meta) {
			$refTable = $meta->getRefTableFqn();

			if ($isCaseSensitive) {
				if ($refTable === $sourceTable && $sourceId === null) {
					$sourceId = $column;
				} elseif ($refTable === $targetTable) {
					$targetId = $column;
				}
			} else {
				if (strcasecmp($refTable, $sourceTable) === 0 && $sourceId === null) {
					$sourceId = $column;
				} elseif (strcasecmp($refTable, $targetTable) === 0) {
					$targetId = $column;
				}
			}
		}

		if ($sourceId === null || $targetId === null) {
			throw new InvalidStateException("No primary keys detected for many has many '{$joinTable}' join table.");
		}

		return [$sourceId, $targetId];
	}


	/**
	 * @phpstan-return array{
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

		$columns = array_keys($this->platform->getForeignKeys($this->storageTable->getNameFqn()));
		foreach ($columns as $storageKey) {
			$entityKey = $this->inflector->formatAsRelationshipProperty($storageKey);
			$mappings[self::TO_ENTITY][$storageKey] = [$entityKey];
			$mappings[self::TO_STORAGE][$entityKey] = [$storageKey];
		}

		/** @phpstan-var list<array{EntityMetadata, list<string>}> $toProcess */
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
					/** @phpstan-var list<string> $propertyTokens */
					$propertyTokens = $baseTokens;
					$propertyTokens[] = $subProperty->name;

					$propertyKey = implode('->', $propertyTokens);
					$storageKey = implode(
						$this->embeddableSeparatorPattern,
						array_map(function ($key): string {
							return $this->inflector->formatAsColumn($key);
						}, $propertyTokens)
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

		switch ($this->platform->getName()) {
			case 'pgsql':
				$types = [
					'TIMESTAMP' => true,
					'DATE' => true,
				];
				break;

			case 'mysql':
				$types = [
					'DATETIME' => true,
					'DATE' => true,
				];
				break;

			case 'mssql':
				$types = [
					'TIMESTAMP' => true,
					'DATE' => true,
				];
				break;

			default:
				throw new NotSupportedException();
		}

		foreach ($this->platform->getColumns($this->storageTable->getNameFqn()) as $column) {
			if (isset($types[$column->type])) {
				$modifiers[$column->name] = '%?dts';
			}
		}

		return $modifiers;
	}
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nette\Utils\Arrays;
use Nette\Utils\Json;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\AnyAggregator;
use Nextras\Orm\Collection\Aggregations\IAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function array_slice;
use function array_unshift;
use function assert;
use function count;
use function implode;
use function is_array;
use function md5;
use function preg_match;
use function reset;


/**
 * QueryBuilder helper for Nextras Dbal.
 */
class DbalQueryBuilderHelper
{
	/** @var IModel */
	private $model;

	/** @var IRepository<IEntity> */
	private $repository;

	/** @var DbalMapper<IEntity> */
	private $mapper;

	/** @var string */
	private $platformName;


	/**
	 * Returns suitable table alias, strips db/schema name and prepends expression $tokens as part of the table name.
	 * @phpstan-param array<int, string> $tokens
	 */
	public static function getAlias(string $name, array $tokens = []): string
	{
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m) === 1) {
			$name = $m[2];
		}

		if (count($tokens) === 0) {
			return $name;
		} else {
			return implode('_', $tokens) . '_' . $name;
		}
	}


	/**
	 * @param IRepository<IEntity> $repository
	 * @param DbalMapper<IEntity> $mapper
	 */
	public function __construct(IModel $model, IRepository $repository, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->repository = $repository;
		$this->mapper = $mapper;
		$this->platformName = $mapper->getDatabasePlatform()->getName();
	}


	/**
	 * Processes a property expression represented by either string or collection function array expression.
	 * @param string|mixed[] $expr
	 */
	public function processPropertyExpr(
		QueryBuilder $builder,
		$expr,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		if (is_array($expr)) {
			$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
			$collectionFunction = $this->getCollectionFunction($function);
			return $collectionFunction->processQueryBuilderExpression($this, $builder, $expr, $aggregator);
		}

		[$tokens, $sourceEntity] = $this->repository->getConditionParser()->parsePropertyExpr($expr);
		return $this->processTokens($tokens, $sourceEntity, $builder, $aggregator);
	}


	/**
	 * Processes an array expression when the first argument at 0 is a collection function name
	 * and the rest are function argument. If the function name is not present, an implicit
	 * {@link ConjunctionOperatorFunction} is used.
	 *
	 * @phpstan-param array<string, mixed>|array<int|string, mixed>|list<mixed> $expr
	 */
	public function processFilterFunction(
		QueryBuilder $builder,
		array $expr,
		?IDbalAggregator $aggregator
	): DbalExpressionResult
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
		$collectionFunction = $this->getCollectionFunction($function);
		return $collectionFunction->processQueryBuilderExpression($this, $builder, $expr, $aggregator);
	}


	/**
	 * @phpstan-return array{string, list<mixed>}
	 */
	public function processOrderDirection(DbalExpressionResult $expression, string $direction): array
	{
		$args = $expression->getArgumentsForExpansion();
		if ($this->platformName === 'mysql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex IS NULL, %ex ASC', $args, $args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex IS NOT NULL, %ex DESC', $args, $args];
			}
		} elseif ($this->platformName === 'mssql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['CASE WHEN %ex IS NULL THEN 1 ELSE 0 END, %ex ASC', $args, $args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['CASE WHEN %ex IS NOT NULL THEN 1 ELSE 0 END, %ex DESC', $args, $args];
			}
		} elseif ($this->platformName === 'pgsql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex ASC', $args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex DESC', $args];
			} elseif ($direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC NULLS FIRST', $args];
			} elseif ($direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC NULLS LAST', $args];
			}
		}

		throw new NotSupportedException();
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public function normalizeValue($value, PropertyMetadata $propertyMetadata, IConventions $conventions)
	{
		if (isset($propertyMetadata->types['array'])) {
			if (is_array($value) && !is_array(reset($value))) {
				$value = [$value];
			}
			if ($propertyMetadata->isPrimary) {
				foreach ($value as $subValue) {
					if (!Arrays::isList($subValue)) {
						throw new InvalidArgumentException('Composite primary value has to be passed as a list, without array keys.');
					}
				}
			}
		}

		if ($propertyMetadata->wrapper !== null) {
			$property = $propertyMetadata->getWrapperPrototype();
			if (is_array($value)) {
				$value = array_map(function ($subValue) use ($property) {
					return $property->convertToRawValue($subValue);
				}, $value);
			} else {
				$value = $property->convertToRawValue($value);
			}
		}

		$tmp = $conventions->convertEntityToStorage([$propertyMetadata->name => $value]);
		$value = reset($tmp);

		return $value;
	}


	/**
	 * @param literal-string $dbalModifier
	 * @param array<DbalJoinEntry> $joins
	 * @return array<DbalJoinEntry>
	 */
	public function mergeJoins(string $dbalModifier, array $joins): array
	{
		if (count($joins) === 0) return [];

		/** @var array<array<DbalJoinEntry>> $aggregated */
		$aggregated = [];
		foreach ($joins as $join) {
			$hash = md5(Json::encode([$join->onExpression, $join->onArgs]));
			/**
			 * We aggregate only by alias as we assume that having a different alias
			 * for different select-from expressions is a responsibility of the query-helper/user.
			 */
			$aggregated[$join->toAlias][$hash] = $join;
		}

		$merged = [];
		foreach ($aggregated as $sameJoins) {
			$first = reset($sameJoins);
			if (count($sameJoins) === 1) {
				$merged[] = $first;
			} else {
				$args = [];
				foreach ($sameJoins as $sameJoin) {
					$joinArgs = $sameJoin->onArgs;
					array_unshift($joinArgs, $sameJoin->onExpression);
					$args[] = $joinArgs;
				}
				$merged[] = new DbalJoinEntry(
					$first->toExpression,
					$first->toArgs,
					$first->toAlias,
					$dbalModifier,
					[$args],
					$first->conventions
				);
			}
		}

		return $merged;
	}


	private function getCollectionFunction(string $name): IQueryBuilderFunction
	{
		$collectionFunction = $this->repository->getCollectionFunction($name);
		if (!$collectionFunction instanceof IQueryBuilderFunction) {
			throw new InvalidArgumentException("Collection function $name has to implement " . IQueryBuilderFunction::class . ' interface.');
		}
		return $collectionFunction;
	}


	/**
	 * @param array<string> $tokens
	 * @param class-string<IEntity>|null $sourceEntity
	 */
	private function processTokens(
		array $tokens,
		?string $sourceEntity,
		QueryBuilder $builder,
		?IDbalAggregator $aggregator
	): DbalExpressionResult
	{
		$lastToken = array_pop($tokens);
		assert($lastToken !== null);

		$currentMapper = $this->mapper;
		$currentAlias = $builder->getFromAlias();
		assert($currentAlias !== null);

		$currentConventions = $currentMapper->getConventions();
		$currentEntityMetadata = $currentMapper->getRepository()->getEntityMetadata($sourceEntity);
		$propertyPrefixTokens = "";
		$makeDistinct = false;

		/** @var DbalJoinEntry[] $joins */
		$joins = [];

		foreach ($tokens as $tokenIndex => $token) {
			$property = $currentEntityMetadata->getProperty($token);
			if ($property->relationship !== null) {
				[
					$currentAlias,
					$currentConventions,
					$currentEntityMetadata,
					$currentMapper,
				] = $this->processRelationship(
					$tokens,
					$joins,
					$property,
					$aggregator,
					$currentConventions,
					$currentMapper,
					$currentAlias,
					$token,
					$tokenIndex,
					$makeDistinct
				);

			} elseif ($property->wrapper === EmbeddableContainer::class) {
				assert($property->args !== null);
				$currentEntityMetadata = $property->args[EmbeddableContainer::class]['metadata'];
				$propertyPrefixTokens .= "$token->";

			} else {
				throw new InvalidArgumentException("Entity {$currentEntityMetadata->className}::\${$token} does not contain a relationship or an embeddable.");
			}
		}

		$propertyMetadata = $currentEntityMetadata->getProperty($lastToken);
		if ($propertyMetadata->wrapper === EmbeddableContainer::class) {
			$propertyExpression = implode('->', array_merge($tokens, [$lastToken]));
			throw new InvalidArgumentException("Property expression '$propertyExpression' does not fetch specific property.");
		}

		$modifier = '';
		$column = $this->toColumnExpr(
			$currentEntityMetadata,
			$propertyMetadata,
			$currentConventions,
			$currentAlias,
			$propertyPrefixTokens,
			$modifier
		);

		if ($makeDistinct) {
			$groupBy = $this->makeDistinct($builder, $this->mapper);
		} else {
			$groupBy = [['%column', $column]];
		}

		return new DbalExpressionResult(
			'%column',
			[$column],
			$joins,
			$groupBy,
			$makeDistinct ? ($aggregator ?? new AnyAggregator()) : null,
			$makeDistinct,
			$propertyMetadata,
			function ($value) use ($propertyMetadata, $currentConventions) {
				return $this->normalizeValue($value, $propertyMetadata, $currentConventions);
			},
			$modifier
		);
	}


	/**
	 * @param array<string> $tokens
	 * @param DbalJoinEntry[] $joins
	 * @param DbalMapper<IEntity> $currentMapper
	 * @param mixed $token
	 * @return array{string, IConventions, EntityMetadata, DbalMapper<IEntity>}
	 */
	private function processRelationship(
		array $tokens,
		array &$joins,
		PropertyMetadata $property,
		?IAggregator $aggregator,
		IConventions $currentConventions,
		DbalMapper $currentMapper,
		string $currentAlias,
		$token,
		int $tokenIndex,
		bool &$makeDistinct
	): array
	{
		assert($property->relationship !== null);
		$targetMapper = $this->model->getRepository($property->relationship->repository)->getMapper();
		assert($targetMapper instanceof DbalMapper);

		$targetConventions = $targetMapper->getConventions();
		$targetEntityMetadata = $property->relationship->entityMetadata;

		$relType = $property->relationship->type;

		if ($relType === Relationship::ONE_HAS_ONE && !$property->relationship->isMain) {
			assert($property->relationship->property !== null);
			$toColumn = $targetConventions->convertEntityToStorageKey($property->relationship->property);
			$fromColumn = $currentConventions->getStoragePrimaryKey()[0];

		} elseif ($relType === Relationship::ONE_HAS_ONE || $relType === Relationship::MANY_HAS_ONE) {
			$toColumn = $targetConventions->getStoragePrimaryKey()[0];
			$fromColumn = $currentConventions->convertEntityToStorageKey($token);

		} elseif ($relType === Relationship::ONE_HAS_MANY) {
			$makeDistinct = true;

			assert($property->relationship->property !== null);
			$toColumn = $targetConventions->convertEntityToStorageKey($property->relationship->property);
			$fromColumn = $currentConventions->getStoragePrimaryKey()[0];

		} elseif ($relType === Relationship::MANY_HAS_MANY) {
			$makeDistinct = true;

			$toColumn = $targetConventions->getStoragePrimaryKey()[0];
			$fromColumn = $currentConventions->getStoragePrimaryKey()[0];

			if ($property->relationship->isMain) {
				[$joinTable, [$inColumn, $outColumn]] =
					$currentMapper->getManyHasManyParameters($property, $targetMapper);
			} else {
				assert($property->relationship->property !== null);
				$sourceProperty = $targetEntityMetadata->getProperty($property->relationship->property);
				[$joinTable, [$outColumn, $inColumn]] =
					$targetMapper->getManyHasManyParameters($sourceProperty, $currentMapper);
			}

			/** @phpstan-var literal-string $joinAlias */
			$joinAlias = self::getAlias($joinTable, array_slice($tokens, 0, $tokenIndex));
			$joins[] = new DbalJoinEntry(
				"%table",
				[$joinTable],
				$joinAlias,
				"%table.%column = %table.%column",
				[$currentAlias, $fromColumn, $joinAlias, $inColumn],
				$currentConventions
			);

			$currentAlias = $joinAlias;
			$fromColumn = $outColumn;

		} else {
			throw new InvalidStateException('Should not happen.');
		}

		$targetTable = $targetMapper->getTableName();
		/** @phpstan-var literal-string $targetAlias */
		$targetAlias = self::getAlias($tokens[$tokenIndex], array_slice($tokens, 0, $tokenIndex));
		if ($makeDistinct) {
			$aggregator = $aggregator ?? new AnyAggregator();
			$targetAlias .= '_' . $aggregator->getAggregateKey();
		}
		$joins[] = new DbalJoinEntry(
			"%table",
			[$targetTable],
			$targetAlias,
			"%table.%column = %table.%column",
			[$currentAlias, $fromColumn, $targetAlias, $toColumn],
			$targetConventions
		);

		return [$targetAlias, $targetConventions, $targetEntityMetadata, $targetMapper];
	}


	/**
	 * @return string|array<string>
	 */
	private function toColumnExpr(
		EntityMetadata $entityMetadata,
		PropertyMetadata $propertyMetadata,
		IConventions $conventions,
		string $alias,
		string $propertyPrefixTokens,
		string &$modifier
	)
	{
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				$modifiers = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $columnName);
					$pair[] = "{$alias}.{$columnName}";
					$modifiers[] = $conventions->getModifier($columnName);
				}
				$modifier = implode(',', $modifiers);
				return $pair;
			} else {
				$propertyName = $primaryKey[0];
			}
		} else {
			$propertyName = $propertyMetadata->name;
		}

		$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $propertyName);
		$modifier = $conventions->getModifier($columnName);
		$columnExpr = "{$alias}.{$columnName}";
		return $columnExpr;
	}


	/**
	 * @param DbalMapper<IEntity> $mapper
	 * @return array<array<mixed>>
	 */
	private function makeDistinct(QueryBuilder $builder, DbalMapper $mapper): array
	{
		$baseTable = $builder->getFromAlias();
		if ($this->platformName === 'mssql') {
			$tableName = $mapper->getTableName();
			$columns = $mapper->getDatabasePlatform()->getColumns($tableName);
			$columnNames = array_map(function (Column $column) use ($tableName): string {
				return $tableName . '.' . $column->name;
			}, $columns);
			return [['%column[]', $columnNames]];

		} else {
			$primaryKey = $this->mapper->getConventions()->getStoragePrimaryKey();

			$groupBy = [];
			foreach ($primaryKey as $column) {
				$groupBy[] = "{$baseTable}.{$column}";
			}

			return [['%column[]', $groupBy]];
		}
	}
}

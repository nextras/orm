<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nette\Utils\Arrays;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
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
use function assert;
use function count;
use function implode;
use function is_array;
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
	 *
	 * If you provide expression mutator, the has-many relationship processing for string property expression uses this
	 * mutator's result for the JOIN clause condition and the returned result value is constructed as an aggregation
	 * check if the wanted table was actually joined at least once. Not providing mutator processes the expression
	 * without JOIN clause modification.
	 *
	 * @param string|mixed[] $expr
	 * @phpstan-param (callable(DbalExpressionResult): DbalExpressionResult)|null $joinExpressionMutator callback processing expression
	 */
	public function processPropertyExpr(
		QueryBuilder $builder,
		$expr,
		?callable $joinExpressionMutator = null
	): DbalExpressionResult
	{
		if (is_array($expr)) {
			$function = array_shift($expr);
			$collectionFunction = $this->getCollectionFunction($function);
			$expression = $collectionFunction->processQueryBuilderExpression($this, $builder, $expr);
			if ($joinExpressionMutator != null) {
				return $joinExpressionMutator($expression);
			} else {
				return $expression;
			}
		}

		[$tokens, $sourceEntity] = $this->repository->getConditionParser()->parsePropertyExpr($expr);
		return $this->processTokens($tokens, $sourceEntity, $builder, $joinExpressionMutator);
	}


	/**
	 * Processes an array expression when the first argument at 0 is a collection function name
	 * and the rest are function argument. If the function name is not present, an implicit
	 * {@link ConjunctionOperatorFunction} is used.
	 *
	 * @phpstan-param array<string, mixed>|array<int|string, mixed>|list<mixed> $expr
	 */
	public function processFilterFunction(QueryBuilder $builder, array $expr): DbalExpressionResult
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection:: AND;
		$collectionFunction = $this->getCollectionFunction($function);
		return $collectionFunction->processQueryBuilderExpression($this, $builder, $expr);
	}


	/**
	 * @param string|array $expr
	 * @phpstan-param string|list<mixed> $expr
	 * @phpstan-return array{string, list<mixed>}
	 */
	public function processOrder(QueryBuilder $builder, $expr, string $direction): array
	{
		$columnReference = $this->processPropertyExpr($builder, $expr);
		return $this->processOrderDirection($columnReference, $direction);
	}


	/**
	 * @phpstan-return array{string, list<mixed>}
	 */
	private function processOrderDirection(DbalExpressionResult $expression, string $direction): array
	{
		if ($this->platformName === 'mysql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $expression->args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $expression->args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex IS NULL, %ex ASC', $expression->args, $expression->args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex IS NOT NULL, %ex DESC', $expression->args, $expression->args];
			}
		} elseif ($this->platformName === 'mssql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $expression->args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $expression->args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['CASE WHEN %ex IS NULL THEN 1 ELSE 0 END, %ex ASC', $expression->args, $expression->args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['CASE WHEN %ex IS NOT NULL THEN 1 ELSE 0 END, %ex DESC', $expression->args, $expression->args];
			}
		} elseif ($this->platformName === 'pgsql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex ASC', $expression->args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex DESC', $expression->args];
			} elseif ($direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC NULLS FIRST', $expression->args];
			} elseif ($direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC NULLS LAST', $expression->args];
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
	 * @param class-string<\Nextras\Orm\Entity\IEntity>|null $sourceEntity
	 * @phpstan-param (callable(DbalExpressionResult): DbalExpressionResult)|null $joinExpressionMutator
	 */
	private function processTokens(
		array $tokens,
		?string $sourceEntity,
		QueryBuilder $builder,
		?callable $joinExpressionMutator
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

		if ($makeDistinct) {
			$this->makeDistinct($builder, $this->mapper);
		}

		$propertyMetadata = $currentEntityMetadata->getProperty($lastToken);
		if ($propertyMetadata->wrapper === EmbeddableContainer::class) {
			$propertyExpression = implode('->', array_merge($tokens, [$lastToken]));
			throw new InvalidArgumentException("Property expression '$propertyExpression' does not fetch specific property.");
		}

		$column = $this->toColumnExpr(
			$currentEntityMetadata,
			$propertyMetadata,
			$currentConventions,
			$currentAlias,
			$propertyPrefixTokens
		);

		$expression = new DbalExpressionResult(
			['%column', $column],
			false,
			$propertyMetadata,
			function ($value) use ($propertyMetadata, $currentConventions) {
				return $this->normalizeValue($value, $propertyMetadata, $currentConventions);
			}
		);

		if ($makeDistinct && $joinExpressionMutator !== null) {
			$joinLast = array_pop($joins);
			foreach ($joins as [$target, $on]) {
				$builder->joinLeft($target, $on);
			}

			/** @var DbalExpressionResult $joinExpressionResult */
			$joinExpressionResult = $joinExpressionMutator($expression);
			$joinExpression = array_shift($joinExpressionResult->args);

			$tableName = $currentMapper->getTableName();
			$tableAlias = $joinLast[2];
			$primaryKey = $currentConventions->getStoragePrimaryKey()[0];

			$builder->joinLeft(
				"[$tableName] as [$tableAlias]",
				"({$joinLast[1]}) AND $joinExpression",
				...$joinExpressionResult->args
			);
			$builder->addGroupBy("%table.%column", $tableAlias, $primaryKey);
			return new DbalExpressionResult(['COUNT(%table.%column) > 0', $tableAlias, $primaryKey], true);

		} elseif ($joinExpressionMutator !== null) {
			foreach ($joins as [$target, $on]) {
				$builder->joinLeft($target, $on);
			}
			return $joinExpressionMutator($expression);

		} else {
			foreach ($joins as [$target, $on]) {
				$builder->joinLeft($target, $on);
			}
			return $expression;
		}
	}


	/**
	 * @param array<string> $tokens
	 * @phpstan-param list<array{string, string, string}> $joins
	 * @param DbalMapper<IEntity> $currentMapper
	 * @param mixed $token
	 * @return array{string, IConventions, EntityMetadata, DbalMapper<IEntity>}
	 */
	private function processRelationship(
		array $tokens,
		array &$joins,
		PropertyMetadata $property,
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

			$joinAlias = self::getAlias($joinTable, array_slice($tokens, 0, $tokenIndex));
			$joins[] = ["[$joinTable] AS [$joinAlias]", "[$currentAlias.$fromColumn] = [$joinAlias.$inColumn]"];

			$currentAlias = $joinAlias;
			$fromColumn = $outColumn;

		} else {
			throw new InvalidStateException('Should not happen.');
		}

		$targetTable = $targetMapper->getTableName();
		$targetAlias = self::getAlias($tokens[$tokenIndex], array_slice($tokens, 0, $tokenIndex));
		$joins[] = [
			"[$targetTable] as [$targetAlias]",
			"[$currentAlias.$fromColumn] = [$targetAlias.$toColumn]",
			$targetAlias,
		];

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
		string $propertyPrefixTokens
	)
	{
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $columnName);
					$pair[] = "{$alias}.{$columnName}";
				}
				return $pair;
			} else {
				$propertyName = $primaryKey[0];
			}
		} else {
			$propertyName = $propertyMetadata->name;
		}

		$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $propertyName);
		$columnExpr = "{$alias}.{$columnName}";
		return $columnExpr;
	}


	/**
	 * @param DbalMapper<IEntity> $mapper
	 */
	private function makeDistinct(QueryBuilder $builder, DbalMapper $mapper): void
	{
		$isGrouped = $builder->getClause('group')[0] ?? null;
		if ($isGrouped !== null) return;

		$baseTable = $builder->getFromAlias();
		if ($this->platformName === 'mssql') {
			$tableName = $mapper->getTableName();
			$columns = $mapper->getDatabasePlatform()->getColumns($tableName);
			$columnNames = array_map(function (Column $column) use ($tableName): string {
				return $tableName . '.' . $column->name;
			}, $columns);
			$builder->groupBy('%column[]', $columnNames);

		} else {
			$primaryKey = $this->mapper->getConventions()->getStoragePrimaryKey();

			$groupBy = [];
			foreach ($primaryKey as $column) {
				$groupBy[] = "{$baseTable}.{$column}";
			}

			$builder->groupBy('%column[]', $groupBy);
		}
	}
}

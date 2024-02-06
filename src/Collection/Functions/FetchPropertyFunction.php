<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nette\Utils\Arrays;
use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\AnyAggregator;
use Nextras\Orm\Collection\Aggregations\IAggregator;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use function count;
use function get_class;


class FetchPropertyFunction implements CollectionFunction
{
	/**
	 * @param IRepository<IEntity> $repository
	 * @param IMapper<IEntity> $mapper
	 * @param IModel $model
	 */
	public function __construct(
		private readonly IRepository $repository,
		private readonly IMapper $mapper,
		private readonly IModel $model,
	)
	{
	}


	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null,
	): ArrayExpressionResult
	{
		$argsCount = count($args);
		if ($argsCount !== 1) {
			throw new InvalidArgumentException("Expected 1 string argument, $argsCount given.");
		}
		$lhs = $args[0];
		if (!is_string($lhs)) {
			$type = gettype($lhs);
			throw new InvalidArgumentException("Expected one string argument, $type type given.");
		}

		[$tokens, $sourceEntityClassName] = $this->repository->getConditionParser()->parsePropertyExpr($lhs);
		$sourceEntityMeta = $this->repository->getEntityMetadata($sourceEntityClassName);
		return $this->getValueByTokens($helper, $entity, $tokens, $sourceEntityMeta, $aggregator);
	}


	/**
	 * @param string[] $expressionTokens
	 * @param IArrayAggregator<mixed>|null $aggregator
	 */
	private function getValueByTokens(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $expressionTokens,
		EntityMetadata $sourceEntityMeta,
		?IArrayAggregator $aggregator,
	): ArrayExpressionResult
	{
		if (!$entity instanceof $sourceEntityMeta->className) {
			return new ArrayExpressionResult(
				value: new class {
					public function __toString()
					{
						return "undefined";
					}
				},
			);
		}

		$isMultiValue = false;
		$values = [];
		$stack = [[$entity, $expressionTokens, $sourceEntityMeta]];

		do {
			/** @var array{IEntity,array<string>,EntityMetadata}|null $shift */
			$shift = array_shift($stack);
			assert($shift !== null);
			$value = $shift[0];
			$tokens = $shift[1];
			$entityMeta = $shift[2];

			do {
				$propertyName = array_shift($tokens);
				assert($propertyName !== null);
				$propertyMeta = $entityMeta->getProperty($propertyName); // check if property exists
				// We allow to cycle-through even if $value is null to properly detect $isMultiValue
				// to return related aggregator.
				$value = $value !== null && $value->hasValue($propertyName) ? $value->getValue($propertyName) : null;

				if ($propertyMeta->relationship) {
					$entityMeta = $propertyMeta->relationship->entityMetadata;
					$type = $propertyMeta->relationship->type;
					if ($type === PropertyRelationshipMetadata::MANY_HAS_MANY || $type === PropertyRelationshipMetadata::ONE_HAS_MANY) {
						$isMultiValue = true;
						if ($value !== null) {
							foreach ($value as $subEntity) {
								if ($subEntity instanceof $entityMeta->className) {
									$stack[] = [$subEntity, $tokens, $entityMeta];
								}
							}
						}
						continue 2;
					}
				} elseif ($propertyMeta->wrapper === EmbeddableContainer::class) {
					assert($propertyMeta->args !== null);
					$entityMeta = $propertyMeta->args[EmbeddableContainer::class]['metadata'];
				}
			} while (count($tokens) > 0);

			$values[] = $helper->normalizeValue($value, $propertyMeta, checkMultiDimension: false);
		} while (count($stack) > 0);

		if ($propertyMeta->wrapper === EmbeddableContainer::class) {
			$propertyExpression = implode('->', $expressionTokens);
			throw new InvalidArgumentException("Property expression '$propertyExpression' does not fetch specific property.");
		}

		return new ArrayExpressionResult(
			value: $isMultiValue ? $values : $values[0],
			aggregator: $isMultiValue ? ($aggregator ?? new AnyAggregator()) : null,
			propertyMetadata: $propertyMeta,
		);
	}


	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null,
	): DbalExpressionResult
	{
		$argsCount = count($args);
		if ($argsCount !== 1) {
			throw new InvalidArgumentException("Expected 1 string argument, $argsCount given.");
		}
		$lhs = $args[0];
		if (!is_string($lhs)) {
			$type = gettype($lhs);
			throw new InvalidArgumentException("Expected one string argument, $type type given.");
		}

		[$tokens, $sourceEntity] = $this->repository->getConditionParser()->parsePropertyExpr($lhs);
		return $this->processTokens($tokens, $sourceEntity, $builder, $aggregator);
	}


	/**
	 * @param array<string> $tokens
	 * @param class-string<IEntity>|null $sourceEntity
	 */
	private function processTokens(
		array $tokens,
		?string $sourceEntity,
		QueryBuilder $builder,
		?IDbalAggregator $aggregator,
	): DbalExpressionResult
	{
		$lastToken = array_pop($tokens);
		assert($lastToken !== null);

		$currentMapper = $this->getDbalMapper();
		$currentAlias = $builder->getFromAlias();
		assert($currentAlias !== null);

		$currentConventions = $currentMapper->getConventions();
		$currentEntityMetadata = $currentMapper->getRepository()->getEntityMetadata($sourceEntity);
		$propertyPrefixTokens = "";
		$makeDistinct = false;

		/** @var DbalTableJoin[] $joins */
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
					$makeDistinct,
				);

			} elseif ($property->wrapper === EmbeddableContainer::class) {
				assert($property->args !== null);
				$currentEntityMetadata = $property->args[EmbeddableContainer::class]['metadata'];
				$propertyPrefixTokens .= "$token->";

			} else {
				throw new InvalidArgumentException("Entity $currentEntityMetadata->className::\$$token does not contain a relationship or an embeddable.");
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
			$modifier,
		);

		if ($makeDistinct) {
			$groupBy = $this->makeDistinct($builder, $this->getDbalMapper());
		} else {
			$groupBy = [['%column', $column]];
		}

		return new DbalExpressionResult(
			expression: '%column',
			args: [$column],
			joins: $joins,
			groupBy: $groupBy,
			aggregator: $makeDistinct ? ($aggregator ?? new AnyAggregator()) : null,
			isHavingClause: $makeDistinct,
			propertyMetadata: $propertyMetadata,
			valueNormalizer: function ($value) use ($propertyMetadata, $currentConventions) {
				return $this->normalizeValue($value, $propertyMetadata, $currentConventions);
			},
			dbalModifier: $modifier,
		);
	}


	/**
	 * @param array<string> $tokens
	 * @param DbalTableJoin[] $joins
	 * @param DbalMapper<IEntity> $currentMapper
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
		mixed $token,
		int $tokenIndex,
		bool &$makeDistinct,
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

			/** @var literal-string $joinAlias */
			$joinAlias = DbalQueryBuilderHelper::getAlias($joinTable, array_slice($tokens, 0, $tokenIndex));
			$joins[] = new DbalTableJoin(
				toExpression: "%table",
				toArgs: [$joinTable],
				toAlias: $joinAlias,
				onExpression: "%table.%column = %table.%column",
				onArgs: [$currentAlias, $fromColumn, $joinAlias, $inColumn],
				primaryKeys: [$currentConventions->getStoragePrimaryKey()[0]],
			);

			$currentAlias = $joinAlias;
			$fromColumn = $outColumn;

		} else {
			throw new InvalidStateException('Should not happen.');
		}

		$targetTable = $targetMapper->getTableName();
		/** @var literal-string $targetAlias */
		$targetAlias = DbalQueryBuilderHelper::getAlias($tokens[$tokenIndex], array_slice($tokens, 0, $tokenIndex));
		if ($makeDistinct) {
			$aggregator = $aggregator ?? new AnyAggregator();
			$targetAlias .= '_' . $aggregator->getAggregateKey();
		}
		$joins[] = new DbalTableJoin(
			toExpression: "%table",
			toArgs: [$targetTable],
			toAlias: $targetAlias,
			onExpression: "%table.%column = %table.%column",
			onArgs: [$currentAlias, $fromColumn, $targetAlias, $toColumn],
			primaryKeys: [$targetConventions->getStoragePrimaryKey()[0]],
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
		string &$modifier,
	): array|string
	{
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				$modifiers = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $columnName);
					$pair[] = "$alias.$columnName";
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
		$columnExpr = "$alias.$columnName";
		return $columnExpr;
	}


	/**
	 * @param DbalMapper<IEntity> $mapper
	 * @return array<array<mixed>>
	 */
	private function makeDistinct(QueryBuilder $builder, DbalMapper $mapper): array
	{
		$baseTable = $builder->getFromAlias();
		if ($mapper->getDatabasePlatform()->getName() === 'mssql') {
			$tableName = $mapper->getConventions()->getStorageTable();
			$columns = $mapper->getDatabasePlatform()->getColumns(
				table: $tableName->fqnName->name,
				schema: $tableName->fqnName->schema,
			);
			$columnNames = array_map(function (Column $column) use ($baseTable): string {
				return $baseTable . '.' . $column->name;
			}, $columns);
			return [['%column[]', $columnNames]];

		} else {
			$primaryKey = $this->getDbalMapper()->getConventions()->getStoragePrimaryKey();

			$groupBy = [];
			foreach ($primaryKey as $column) {
				$groupBy[] = "$baseTable.$column";
			}

			return [['%column[]', $groupBy]];
		}
	}


	public function normalizeValue(mixed $value, PropertyMetadata $propertyMetadata, IConventions $conventions): mixed
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
	 * @return DbalMapper<IEntity>
	 */
	private function getDbalMapper(): DbalMapper
	{
		if (!$this->mapper instanceof DbalMapper) {
			$repository = get_class($this->repository);
			$actual = get_class($this->mapper);
			throw new InvalidStateException("Repository $repository must use DbalMapper. $actual used instead.");
		}

		return $this->mapper;
	}
}

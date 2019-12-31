<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Utils\Arrays;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFilterFunction;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFunction;
use Nextras\Orm\Mapper\Dbal\Helpers\ColumnReference;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;


/**
 * QueryBuilder helper for Nextras Dbal.
 */
class QueryBuilderHelper
{
	/** @var IModel */
	private $model;

	/** @var IRepository */
	private $repository;

	/** @var DbalMapper */
	private $mapper;


	public static function getAlias(string $name): string
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}


	public function __construct(IModel $model, IRepository $repository, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->repository = $repository;
		$this->mapper = $mapper;
	}


	public function processApplyFunction(QueryBuilder $builder, string $function, array $expr): QueryBuilder
	{
		$customFunction = $this->repository->getCollectionFunction($function);
		if (!$customFunction instanceof IQueryBuilderFunction) {
			throw new InvalidStateException("Custom function $function has to implement " . IQueryBuilderFunction::class . ' interface.');
		}

		return $customFunction->processQueryBuilderFilter($this, $builder, $expr);
	}


	public function processFilterFunction(QueryBuilder $builder, array $expr): array
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
		$customFunction = $this->repository->getCollectionFunction($function);
		if (!$customFunction instanceof IQueryBuilderFilterFunction) {
			throw new InvalidStateException("Custom function $function has to implement " . IQueryBuilderFilterFunction::class . ' interface.');
		}

		return $customFunction->processQueryBuilderFilter($this, $builder, $expr);
	}


	public function processPropertyExpr(QueryBuilder $builder, string $propertyExpr): ColumnReference
	{
		[$tokens, $sourceEntity] = ConditionParserHelper::parsePropertyExpr($propertyExpr);
		return $this->processTokens($tokens, $sourceEntity, $builder);
	}


	public function normalizeValue($value, ColumnReference $columnReference)
	{
		if (isset($columnReference->propertyMetadata->types['array'])) {
			if (is_array($value) && !is_array(reset($value))) {
				$value = [$value];
			}
			if ($columnReference->propertyMetadata->isPrimary) {
				foreach ($value as $subValue) {
					if (!Arrays::isList($subValue)) {
						throw new InvalidArgumentException('Composite primary value has to be passed as a list, without array keys.');
					}
				}
			}
		}

		if ($columnReference->propertyMetadata->wrapper) {
			$property = $columnReference->propertyMetadata->getWrapperPrototype();
			if (is_array($value)) {
				$value = array_map(function ($subValue) use ($property) {
					return $property->convertToRawValue($subValue);
				}, $value);
			} else {
				$value = $property->convertToRawValue($value);
			}
		}

		$tmp = $columnReference->storageReflection->convertEntityToStorage([$columnReference->propertyMetadata->name => $value]);
		$value = reset($tmp);

		return $value;
	}


	/**
	 * @param array<string> $tokens
	 * @param class-string<\Nextras\Orm\Entity\IEntity>|null $sourceEntity
	 */
	private function processTokens(array $tokens, ?string $sourceEntity, QueryBuilder $builder): ColumnReference
	{
		$lastToken = \array_pop($tokens);
		\assert($lastToken !== null);

		$currentMapper = $this->mapper;
		$currentAlias = $builder->getFromAlias();
		$currentReflection = $currentMapper->getStorageReflection();
		$currentEntityMetadata = $currentMapper->getRepository()->getEntityMetadata($sourceEntity);
		$propertyPrefixTokens = "";

		foreach ($tokens as $tokenIndex => $token) {
			$property = $currentEntityMetadata->getProperty($token);
			if ($property->relationship !== null) {
				[
					$currentAlias,
					$currentReflection,
					$currentEntityMetadata,
					$currentMapper,
				] = $this->processRelationship(
					$tokens,
					$builder,
					$property,
					$currentReflection,
					$currentMapper,
					$currentAlias,
					$token,
					$tokenIndex
				);
			} elseif ($property->wrapper === EmbeddableContainer::class) {
				\assert($property->args !== null);
				$currentEntityMetadata = $property->args[EmbeddableContainer::class]['metadata'];
				$propertyPrefixTokens .= "$token->";
			} else {
				throw new InvalidArgumentException("Entity {$currentEntityMetadata->className}::\${$token} does not contain a relationship or an embeddable.");
			}
		}

		$propertyMetadata = $currentEntityMetadata->getProperty($lastToken);
		$column = $this->toColumnExpr(
			$currentEntityMetadata,
			$propertyMetadata,
			$currentReflection,
			$currentAlias,
			$propertyPrefixTokens
		);

		return new ColumnReference($column, $propertyMetadata, $currentEntityMetadata, $currentReflection);
	}


	/**
	 * @param array<string> $tokens
	 * @param mixed         $token
	 * @param int           $tokenIndex
	 * @return array{string, IConventions, EntityMetadata, DbalMapper}
	 */
	private function processRelationship(
		array $tokens,
		QueryBuilder $builder,
		PropertyMetadata $property,
		IConventions $currentReflection,
		DbalMapper $currentMapper,
		string $currentAlias,
		$token,
		int $tokenIndex
	): array
	{
		\assert($property->relationship !== null);
		$targetMapper = $this->model->getRepository($property->relationship->repository)->getMapper();
		\assert($targetMapper instanceof DbalMapper);

		$targetReflection = $targetMapper->getStorageReflection();
		$targetEntityMetadata = $property->relationship->entityMetadata;

		$relType = $property->relationship->type;
		if ($relType === Relationship::ONE_HAS_MANY) {
			\assert($property->relationship->property !== null);
			$toColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
			$fromColumn = $currentReflection->getStoragePrimaryKey()[0];
			$this->makeDistinct($builder);

		} elseif ($relType === Relationship::ONE_HAS_ONE && !$property->relationship->isMain) {
			\assert($property->relationship->property !== null);
			$toColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
			$fromColumn = $currentReflection->getStoragePrimaryKey()[0];

		} elseif ($relType === Relationship::MANY_HAS_MANY) {
			$toColumn = $targetReflection->getStoragePrimaryKey()[0];
			$fromColumn = $currentReflection->getStoragePrimaryKey()[0];
			$this->makeDistinct($builder);

			if ($property->relationship->isMain) {
				\assert($currentMapper instanceof DbalMapper);
				[
					$joinTable,
					[$inColumn, $outColumn],
				] = $currentMapper->getManyHasManyParameters($property, $targetMapper);

			} else {
				\assert($currentMapper instanceof DbalMapper);
				\assert($property->relationship->property !== null);

				$sourceProperty = $targetEntityMetadata->getProperty($property->relationship->property);
				[
					$joinTable,
					[$outColumn, $inColumn],
				] = $targetMapper->getManyHasManyParameters($sourceProperty, $currentMapper);
			}

			$builder->leftJoin($currentAlias, "[$joinTable]", self::getAlias($joinTable), "[$currentAlias.$fromColumn] = [$joinTable.$inColumn]");
			$currentAlias = $joinTable;
			$fromColumn = $outColumn;

		} else {
			$toColumn = $targetReflection->getStoragePrimaryKey()[0];
			$fromColumn = $currentReflection->convertEntityToStorageKey($token);
		}

		$targetTable = $targetMapper->getTableName();
		$targetAlias = implode('_', array_slice($tokens, 0, $tokenIndex + 1));

		$builder->leftJoin($currentAlias, "[$targetTable]", $targetAlias, "[$currentAlias.$fromColumn] = [$targetAlias.$toColumn]");

		return [$targetAlias, $targetReflection, $targetEntityMetadata, $targetMapper];
	}


	/**
	 * @return string|array<string>
	 */
	private function toColumnExpr(
		EntityMetadata $entityMetadata,
		PropertyMetadata $propertyMetadata,
		IConventions $storageReflection,
		string $alias,
		string $propertyPrefixTokens
	)
	{
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $storageReflection->convertEntityToStorageKey($propertyPrefixTokens . $columnName);
					$pair[] = "{$alias}.{$columnName}";
				}
				return $pair;
			} else {
				$propertyName = $primaryKey[0];
			}
		} else {
			$propertyName = $propertyMetadata->name;
		}

		$columnName = $storageReflection->convertEntityToStorageKey($propertyPrefixTokens . $propertyName);
		$columnExpr = "{$alias}.{$columnName}";
		return $columnExpr;
	}


	private function makeDistinct(QueryBuilder $builder)
	{
		$baseTable = $builder->getFromAlias();
		if ($this->mapper->getDatabasePlatform()->getName() === 'mssql') {
			$builder->select('DISTINCT %table.*', $baseTable);

		} else {
			$primaryKey = $this->mapper->getStorageReflection()->getStoragePrimaryKey();

			$groupBy = [];
			foreach ($primaryKey as $column) {
				$groupBy[] = "{$baseTable}.{$column}";
			}

			$builder->groupBy('%column[]', $groupBy);
		}
	}
}

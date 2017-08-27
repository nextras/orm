<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\Memory\CustomFunctions\IArrayFilterFunction;
use Nextras\Orm\Mapper\Memory\CustomFunctions\IArrayFunction;
use Nextras\Orm\Repository\IRepository;


class ArrayCollectionHelper
{
	/** @var IRepository */
	private $repository;

	/** @var IMapper */
	private $mapper;


	public function __construct(IRepository $repository)
	{
		$this->repository = $repository;
		$this->mapper = $repository->getMapper();
	}


	public function createFunction(string $function, array $expr): Closure
	{
		$customFunction = $this->repository->getCollectionFunction($function);
		if (!$customFunction instanceof IArrayFunction) {
			throw new InvalidStateException("Custom function $function has to implement IQueryBuilderFunction interface.");
		}

		return function (array $entities) use ($customFunction, $expr) {
			/** @var IEntity[] $entities */
			return $customFunction->processArrayFilter($this, $entities, $expr);
		};
	}


	public function createFilter(array $expr): Closure
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
		$customFunction = $this->repository->getCollectionFunction($function);

		if (!$customFunction instanceof IArrayFilterFunction) {
			throw new InvalidStateException("Custom function $function has to implement IQueryBuilderFilterFunction interface.");
		}

		return function (IEntity $entity) use ($customFunction, $expr) {
			return $customFunction->processArrayFilter($this, $entity, $expr);
		};
	}


	public function createSorter(array $conditions): Closure
	{
		$columns = [];
		foreach ($conditions as $pair) {
			list($column, $sourceEntity) = ConditionParserHelper::parsePropertyExpr($pair[0]);
			$sourceEntityMeta = $this->repository->getEntityMetadata($sourceEntity);
			$columns[] = [$column, $pair[1], $sourceEntityMeta];
		}

		return function ($a, $b) use ($columns) {
			foreach ($columns as $pair) {
				$a_ref = $this->getValueByTokens($a, $pair[0], $pair[2]);
				$b_ref = $this->getValueByTokens($b, $pair[0], $pair[2]);
				if ($a_ref === null || $b_ref === null) {
					throw new InvalidStateException('Comparing entities that should not be included in the result. Possible missing filtering configuration for required entity type based on Single Table Inheritance.');
				}
				$_a = $a_ref->value;
				$_b = $b_ref->value;
				$direction = $pair[1] === ICollection::ASC ? 1 : -1;

				if ($_a === null || $_b === null) {
					if ($_a !== $_b) {
						return $direction * ($_a === null ? -1 : 1);
					}
				} elseif (is_int($_a) || is_float($_a)) {
					if ($_a < $_b) {
						return $direction * -1;
					} elseif ($_a > $_b) {
						return $direction;
					}
				} else {
					$res = strcmp((string) $_a, (string) $_b);
					if ($res < 0) {
						return $direction * -1;
					} elseif ($res > 0) {
						return $direction;
					}
				}
			}

			return 0;
		};
	}


	/**
	 * Returns value reference, returns null when entity should not be evaluated at all because of STI condition.
	 * @return ValueReference|null
	 */
	public function getValue(IEntity $entity, string $expr)
	{
		list($tokens, $sourceEntityClassName) = ConditionParserHelper::parsePropertyExpr($expr);
		$sourceEntityMeta = $this->repository->getEntityMetadata($sourceEntityClassName);
		return $this->getValueByTokens($entity, $tokens, $sourceEntityMeta);
	}


	public function normalizeValue($value, PropertyMetadata $propertyMetadata)
	{
		if ($value instanceof IEntity) {
			return $value->hasValue('id') ? $value->getValue('id') : null;
		} elseif (isset($propertyMetadata->types['datetime']) && $value !== null) {
			if (!$value instanceof DateTimeInterface) {
				$value = new DateTimeImmutable($value);
			}
			return $value->getTimestamp();
		}

		return $value;
	}


	/**
	 * @return ValueReference|null
	 */
	private function getValueByTokens(IEntity $entity, array $tokens, EntityMetadata $sourceEntityMeta)
	{
		if (!$entity instanceof $sourceEntityMeta->className) {
			return null;
		}

		$isMultiValue = false;
		$values = [];
		$stack = [[$entity, $tokens, $sourceEntityMeta]];

		do {
			/** @var IEntity $value */
			/** @var string[] $tokens */
			/** @var EntityMetadata $entityMeta */
			list ($value, $tokens, $entityMeta) = array_shift($stack);

			do {
				$propertyName = array_shift($tokens);
				$propertyMeta = $entityMeta->getProperty($propertyName); // check if property exists
				$value = $value->hasValue($propertyName) ? $value->getValue($propertyName) : null;

				if ($propertyMeta->relationship) {
					$entityMeta = $propertyMeta->relationship->entityMetadata;
					$type = $propertyMeta->relationship->type;
					if ($type === PropertyRelationshipMetadata::MANY_HAS_MANY || $type === PropertyRelationshipMetadata::ONE_HAS_MANY) {
						$isMultiValue = true;
						foreach ($value as $subEntity) {
							if ($subEntity instanceof $entityMeta->className) {
								$stack[] = [$subEntity, $tokens, $entityMeta];
							}
						}
						continue 2;
					}
				}
			} while (count($tokens) > 0 && $value !== null);

			$values[] = $this->normalizeValue($value, $propertyMeta);
		} while (!empty($stack));

		return new ValueReference(
			$isMultiValue ? $values : $values[0],
			$isMultiValue,
			$propertyMeta
		);
	}
}

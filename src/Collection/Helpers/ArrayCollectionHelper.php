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
use Nette\Utils\Arrays;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\InvalidArgumentException;
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
			[$column, $sourceEntity] = ConditionParserHelper::parsePropertyExpr($pair[0]);
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
	 */
	public function getValue(IEntity $entity, string $expr): ?ValueReference
	{
		[$tokens, $sourceEntityClassName] = ConditionParserHelper::parsePropertyExpr($expr);
		$sourceEntityMeta = $this->repository->getEntityMetadata($sourceEntityClassName);
		return $this->getValueByTokens($entity, $tokens, $sourceEntityMeta);
	}


	public function normalizeValue($value, PropertyMetadata $propertyMetadata, bool $checkMultiDimension = true)
	{
		if ($checkMultiDimension && isset($propertyMetadata->types['array'])) {
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

		if ($propertyMetadata->container) {
			$property = $propertyMetadata->getPropertyPrototype();
			if (is_array($value)) {
				$value = array_map(function ($subValue) use ($property) {
					return $property->convertToRawValue($subValue);
				}, $value);
			} else {
				$value = $property->convertToRawValue($value);
			}
		} elseif (
			(isset($propertyMetadata->types[\DateTimeImmutable::class]) || isset($propertyMetadata->types[\Nextras\Dbal\Utils\DateTimeImmutable::class]))
			&& $value !== null
		) {
			$converter = static function ($input) {
				if (!$input instanceof DateTimeInterface) {
					$input = new DateTimeImmutable($input);
				}
				return $input->getTimestamp();
			};
			if (is_array($value)) {
				$value = array_map($converter, $value);
			} else {
				$value = $converter($value);
			}
		}

		return $value;
	}


	/**
	 * @param  string[] $tokens
	 */
	private function getValueByTokens(IEntity $entity, array $tokens, EntityMetadata $sourceEntityMeta): ?ValueReference
	{
		if (!$entity instanceof $sourceEntityMeta->className) {
			return null;
		}

		$isMultiValue = false;
		$values = [];
		$stack = [[$entity, $tokens, $sourceEntityMeta]];

		do {
			/** @var array $shift */
			$shift = array_shift($stack);
			/** @var IEntity $value */
			$value = $shift[0];
			/** @var string[] $tokens */
			$tokens = $shift[1];
			/** @var EntityMetadata $entityMeta */
			$entityMeta = $shift[2];

			do {
				$propertyName = array_shift($tokens);
				assert($propertyName !== null);
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

			$values[] = $this->normalizeValue($value, $propertyMeta, false);
		} while (!empty($stack));

		return new ValueReference(
			$isMultiValue ? $values : $values[0],
			$isMultiValue,
			$propertyMeta
		);
	}
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Nette\Utils\Arrays;
use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Repository\IRepository;
use function array_map;
use function array_shift;
use function assert;
use function count;
use function implode;
use function is_array;


class ArrayCollectionHelper
{
	/** @var IRepository */
	private $repository;


	public function __construct(IRepository $repository)
	{
		$this->repository = $repository;
	}


	/**
	 * @phpstan-param array<string, mixed>|list<mixed> $expr
	 * @phpstan-return Closure(IEntity): mixed
	 */
	public function createFilter(array $expr): Closure
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
		$customFunction = $this->repository->getCollectionFunction($function);
		if (!$customFunction instanceof IArrayFunction) {
			throw new InvalidStateException("Collection function $function has to implement " . IArrayFunction::class . ' interface.');
		}

		return function (IEntity $entity) use ($customFunction, $expr) {
			return $customFunction->processArrayExpression($this, $entity, $expr);
		};
	}


	/**
	 * @phpstan-param array<array<string, mixed>|list<mixed>> $expressions
	 * @phpstan-return Closure(IEntity, IEntity): int
	 */
	public function createSorter(array $expressions): Closure
	{
		$conditionParser = $this->repository->getConditionParser();

		$parsedExpressions = [];
		foreach ($expressions as $expression) {
			if (is_array($expression[0])) {
				if (!isset($expression[0][0])) {
					throw new InvalidArgumentException();
				}
				$function = array_shift($expression[0]);
				$collectionFunction = $this->repository->getCollectionFunction($function);
				if (!$collectionFunction instanceof IArrayFunction) {
					throw new InvalidStateException("Collection function $function has to implement " . IArrayFunction::class . ' interface.');
				}
				$parsedExpressions[] = [$collectionFunction, $expression[1], $expression[0]];
			} else {
				[$column, $sourceEntity] = $conditionParser->parsePropertyExpr($expression[0]);
				$sourceEntityMeta = $this->repository->getEntityMetadata($sourceEntity);
				$parsedExpressions[] = [$column, $expression[1], $sourceEntityMeta];
			}
		}

		return function ($a, $b) use ($parsedExpressions): int {
			foreach ($parsedExpressions as $expression) {
				if ($expression[0] instanceof IArrayFunction) {
					assert(is_array($expression[2]));
					$_a = $expression[0]->processArrayExpression($this, $a, $expression[2]);
					$_b = $expression[0]->processArrayExpression($this, $b, $expression[2]);
				} else {
					assert($expression[2] instanceof EntityMetadata);
					$_a = $this->getValueByTokens($a, $expression[0], $expression[2])->value;
					$_b = $this->getValueByTokens($b, $expression[0], $expression[2])->value;
				}

				$ordering = $expression[1];
				$descReverse = ($ordering === ICollection::ASC || $ordering === ICollection::ASC_NULLS_FIRST || $ordering === ICollection::ASC_NULLS_LAST) ? 1 : -1;

				if ($_a === null || $_b === null) {
					// By default, <=> sorts nulls at the beginning.
					$nullsReverse = $ordering === ICollection::ASC_NULLS_FIRST || $ordering === ICollection::DESC_NULLS_FIRST ? 1 : -1;
					$result = ($_a <=> $_b) * $nullsReverse;
				} elseif (is_int($_a) || is_float($_a) || is_int($_b) || is_float($_b)) {
					$result = ($_a <=> $_b) * $descReverse;
				} else {
					$result = ((string) $_a <=> (string) $_b) * $descReverse;
				}

				if ($result !== 0) {
					return $result;
				}
			}

			return 0;
		};
	}


	/**
	 * @param string|array $expr
	 * @phpstan-param string|array<string, mixed>|list<mixed> $expr
	 */
	public function getValue(IEntity $entity, $expr): ArrayPropertyValueReference
	{
		if (is_array($expr)) {
			$function = array_shift($expr);
			$collectionFunction = $this->repository->getCollectionFunction($function);
			if (!$collectionFunction instanceof IArrayFunction) {
				throw new InvalidStateException("Collection function $function has to implement " . IArrayFunction::class . ' interface.');
			}
			$value = $collectionFunction->processArrayExpression($this, $entity, $expr);
			return new ArrayPropertyValueReference($value, false, null);
		}

		[$tokens, $sourceEntityClassName] = $this->repository->getConditionParser()->parsePropertyExpr($expr);
		$sourceEntityMeta = $this->repository->getEntityMetadata($sourceEntityClassName);
		return $this->getValueByTokens($entity, $tokens, $sourceEntityMeta);
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
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

		if ($propertyMetadata->wrapper !== null) {
			$property = $propertyMetadata->getWrapperPrototype();
			if (is_array($value)) {
				$value = array_map(function ($subValue) use ($property) {
					return $property->convertToRawValue($subValue);
				}, $value);
			} else {
				$value = $property->convertToRawValue($value);
			}
		} elseif (
			(isset($propertyMetadata->types[DateTimeImmutable::class]) || isset($propertyMetadata->types[\Nextras\Dbal\Utils\DateTimeImmutable::class]))
			&& $value !== null
		) {
			$converter = static function ($input): int {
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
	 * @param string[] $expressionTokens
	 */
	private function getValueByTokens(
		IEntity $entity,
		array $expressionTokens,
		EntityMetadata $sourceEntityMeta
	): ArrayPropertyValueReference
	{
		if (!$entity instanceof $sourceEntityMeta->className) {
			return new ArrayPropertyValueReference(
				new class {
					public function __toString()
					{
						return "undefined";
					}
				},
				false,
				null
			);
		}

		$isMultiValue = false;
		$values = [];
		$stack = [[$entity, $expressionTokens, $sourceEntityMeta]];

		do {
			/** @var (array{IEntity,array<string>,EntityMetadata}) $shift */
			$shift = array_shift($stack);
			assert($shift !== null);
			$value = $shift[0];
			$tokens = $shift[1];
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
				} elseif ($propertyMeta->wrapper === EmbeddableContainer::class) {
					assert($propertyMeta->args !== null);
					$entityMeta = $propertyMeta->args[EmbeddableContainer::class]['metadata'];
				}
			} while (count($tokens) > 0 && $value !== null);

			$values[] = $this->normalizeValue($value, $propertyMeta, false);
		} while (count($stack) > 0);

		if ($propertyMeta->wrapper === EmbeddableContainer::class) {
			$propertyExpression = implode('->', $expressionTokens);
			throw new InvalidArgumentException("Property expression '$propertyExpression' does not fetch specific property.");
		}

		return new ArrayPropertyValueReference(
			$isMultiValue ? $values : $values[0],
			$isMultiValue,
			$propertyMeta
		);
	}
}

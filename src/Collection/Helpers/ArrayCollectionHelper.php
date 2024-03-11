<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Nette\Utils\Arrays;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\CollectionFunction;
use Nextras\Orm\Collection\Functions\FetchPropertyFunction;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Repository\IRepository;
use function array_map;
use function array_shift;
use function is_array;


class ArrayCollectionHelper
{
	/**
	 * @param IRepository<IEntity> $repository
	 */
	public function __construct(
		private readonly IRepository $repository,
	)
	{
	}


	/**
	 * @param array<mixed> $expr
	 * @param Aggregator<mixed>|null $aggregator
	 * @return Closure(IEntity): ArrayExpressionResult
	 */
	public function createFilter(array $expr, ?Aggregator $aggregator): Closure
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
		$customFunction = $this->repository->getCollectionFunction($function);
		return function (IEntity $entity) use ($customFunction, $expr, $aggregator) {
			return $customFunction->processArrayExpression($this, $entity, $expr, $aggregator);
		};
	}


	/**
	 * @param array<array<string, mixed>|list<mixed>> $expressions
	 * @return Closure(IEntity, IEntity): int
	 */
	public function createSorter(array $expressions): Closure
	{
		/** @var list<array{CollectionFunction, string, array<mixed>}> $parsedExpressions */
		$parsedExpressions = [];
		$fetchPropertyFunction = $this->repository->getCollectionFunction(FetchPropertyFunction::class);
		foreach ($expressions as $expression) {
			if (is_array($expression[0])) {
				if (!isset($expression[0][0])) {
					throw new InvalidArgumentException();
				}
				$function = array_shift($expression[0]);
				$collectionFunction = $this->repository->getCollectionFunction($function);
				$parsedExpressions[] = [$collectionFunction, $expression[1], $expression[0]];
			} else {
				$parsedExpressions[] = [$fetchPropertyFunction, $expression[1], [$expression[0]]];
			}
		}

		return function ($a, $b) use ($parsedExpressions): int {
			foreach ($parsedExpressions as [$function, $ordering, $functionArgs]) {
				$_a = $function->processArrayExpression($this, $a, $functionArgs)->value;
				$_b = $function->processArrayExpression($this, $b, $functionArgs)->value;

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
	 * @param string|array<string, mixed>|list<mixed> $expression
	 * @param Aggregator<mixed>|null $aggregator
	 */
	public function getValue(
		IEntity $entity,
		array|string $expression,
		?Aggregator $aggregator,
	): ArrayExpressionResult
	{
		if (is_string($expression)) {
			$function = FetchPropertyFunction::class;
			$collectionFunction = $this->repository->getCollectionFunction($function);
			$expression = [$expression];
		} else {
			$function = isset($expression[0]) ? array_shift($expression) : ICollection::AND;
			$collectionFunction = $this->repository->getCollectionFunction($function);
		}

		return $collectionFunction->processArrayExpression($this, $entity, $expression, $aggregator);
	}


	public function normalizeValue(
		mixed $value,
		PropertyMetadata $propertyMetadata,
		bool $checkMultiDimension = true,
	): mixed
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
}

<?php declare(strict_types=1);

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
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\Relationships\IRelationshipCollection;


class ArrayCollectionHelper
{
	/** @var IModel */
	private $model;

	/** @var IMapper */
	private $mapper;

	/** @var MetadataStorage */
	private $metadataStorage;


	public function __construct(IModel $model, IMapper $mapper)
	{
		$this->model = $model;
		$this->mapper = $mapper;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	public function createFilter(array $conditions): Closure
	{
		if (!isset($conditions[0])) {
			$operator = ICollection::AND;
		} else {
			$operator = $conditions[0];
			$conditions = $conditions[1];
		}

		$callbacks = [];
		foreach ($conditions as $expression => $value) {
			if (is_int($expression)) {
				$callbacks[] = $this->createFilter($value);
			} else {
				$callbacks[] = $this->createExpressionFilter($expression, $value);
			}
		}

		if ($operator === ICollection::AND) {
			return function ($value) use ($callbacks) {
				foreach ($callbacks as $callback) {
					if (!$callback($value)) {
						return false;
					}
				}
				return true;
			};
		} elseif ($operator === ICollection::OR) {
			return function ($value) use ($callbacks) {
				foreach ($callbacks as $callback) {
					if ($callback($value)) {
						return true;
					}
				}
				return false;
			};
		} else {
			throw new NotSupportedException("Operator $operator is not supported");
		}
	}


	/**
	 * @param  mixed  $value
	 */
	public function createExpressionFilter(string $condition, $value): Closure
	{
		list($chain, $operator, $sourceEntity) = ConditionParserHelper::parseCondition($condition);
		$sourceEntityMeta = $this->metadataStorage->get($sourceEntity ?: $this->mapper->getRepository()->getEntityClassNames()[0]);

		if ($value instanceof IEntity) {
			$value = $value->getValue('id');
		}

		$comparator = $this->createComparator($operator, is_array($value));
		return $this->createFilterEvaluator($chain, $comparator, $sourceEntityMeta, $value);
	}


	private function createComparator(string $operator, bool $isArray): Closure
	{
		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if ($isArray) {
				return function ($property, $value) {
					return in_array($property, $value, true);
				};
			} else {
				return function ($property, $value) {
					return $property === $value;
				};
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if ($isArray) {
				return function ($property, $value) {
					return !in_array($property, $value, true);
				};
			} else {
				return function ($property, $value) {
					return $property !== $value;
				};
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_GREATER) {
			return function ($property, $value) {
				return $property > $value;
			};
		} elseif ($operator === ConditionParserHelper::OPERATOR_EQUAL_OR_GREATER) {
			return function ($property, $value) {
				return $property >= $value;
			};
		} elseif ($operator === ConditionParserHelper::OPERATOR_SMALLER) {
			return function ($property, $value) {
				return $property < $value;
			};
		} elseif ($operator === ConditionParserHelper::OPERATOR_EQUAL_OR_SMALLER) {
			return function ($property, $value) {
				return $property <= $value;
			};
		} else {
			throw new InvalidArgumentException();
		}
	}


	protected function createFilterEvaluator(array $chainSource, Closure $predicate, EntityMetadata $sourceEntityMetaSource, $targetValue): Closure
	{
		$evaluator = function (
			IEntity $element,
			array $chain = null,
			EntityMetadata $sourceEntityMeta = null
		) use (
			& $evaluator,
			$predicate,
			$chainSource,
			$sourceEntityMetaSource,
			$targetValue
		): bool {
			if (!$chain) {
				$sourceEntityMeta = $sourceEntityMetaSource;
				$chain = $chainSource;
			}

			$column = array_shift($chain);
			$propertyMeta = $sourceEntityMeta->getProperty($column); // check if property exists
			$value = $element->hasValue($column) ? $element->getValue($column) : null;

			if (!$chain) {
				if ($column === 'id' && count($sourceEntityMeta->getPrimaryKey()) > 1 && !isset($targetValue[0][0])) {
					$targetValue = [$targetValue];
				}
				return $predicate(
					$this->normalizeValue($value, $propertyMeta),
					$this->normalizeValue($targetValue, $propertyMeta)
				);
			}

			$targetEntityMeta = $this->metadataStorage->get($propertyMeta->relationship->entity);
			if ($value === null) {
				return false;

			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value as $node) {
					if ($evaluator($node, $chain, $targetEntityMeta)) {
						return true;
					}
				}

				return false;
			} else {
				return $evaluator($value, $chain, $targetEntityMeta);
			}
		};

		return $evaluator;
	}


	public function createSorter(array $conditions): Closure
	{
		$columns = [];
		foreach ($conditions as $pair) {
			list($column, , $sourceEntity) = ConditionParserHelper::parseCondition($pair[0]);
			$sourceEntityMeta = $this->metadataStorage->get($sourceEntity ?: $this->mapper->getRepository()->getEntityClassNames()[0]);
			$columns[] = [$column, $pair[1], $sourceEntityMeta];
		}

		return function ($a, $b) use ($columns) {
			foreach ($columns as $pair) {
				$_a = $this->getter($a, $pair[0], $pair[2]);
				$_b = $this->getter($b, $pair[0], $pair[2]);
				$direction = $pair[1] === ICollection::ASC ? 1 : -1;

				if (is_int($_a) || is_float($_a)) {
					if ($_a < $_b) {
						return $direction * -1;
					} elseif ($_a > $_b) {
						return $direction;
					}
				} elseif ($_a instanceof DateTimeInterface) {
					if ($_a < $_b) {
						return $direction * -1;
					} elseif ($_b > $_a) {
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


	public function getter(IEntity $element, array $chain, EntityMetadata $sourceEntityMeta)
	{
		$column = array_shift($chain);
		$propertyMeta = $sourceEntityMeta->getProperty($column); // check if property exists
		$value = $element->hasValue($column) ? $element->getValue($column) : null;

		if ($value instanceof IRelationshipCollection) {
			throw new InvalidStateException('You can not sort by hasMany relationship.');
		}

		if (!$chain) {
			return $this->normalizeValue($value, $propertyMeta);
		} else {
			$targetEntityMeta = $this->metadataStorage->get($propertyMeta->relationship->entity);
			return $this->getter($value, $chain, $targetEntityMeta);
		}
	}


	private function normalizeValue($value, PropertyMetadata $propertyMetadata)
	{
		if ($value instanceof IEntity) {
			return $value->hasValue('id') ? $value->getValue('id') : null;

		} elseif (isset($propertyMetadata->types['datetime']) && $value !== null && !$value instanceof DateTimeInterface) {
			return new DateTimeImmutable($value);
		}

		return $value;
	}
}

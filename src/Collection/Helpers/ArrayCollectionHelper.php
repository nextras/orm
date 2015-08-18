<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Closure;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
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


	/**
	 * @param  string $condition
	 * @param  mixed  $value
	 * @return Closure
	 */
	public function createFilter($condition, $value)
	{
		list($chain, $operator, $sourceEntity) = ConditionParserHelper::parseCondition($condition);
		$sourceEntityMeta = $this->metadataStorage->get($sourceEntity ?: $this->mapper->getRepository()->getEntityClassNames()[0]);

		if ($value instanceof IEntity) {
			$value = $value->getValue('id');
		}

		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (is_array($value)) {
				$predicate = function($property, $value) {
					return in_array($property, $value, TRUE);
				};
			} else {
				$predicate = function($property, $value) {
					return $property === $value;
				};
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (is_array($value)) {
				$predicate = function($property, $value) {
					return !in_array($property, $value, TRUE);
				};
			} else {
				$predicate = function($property, $value) {
					return $property !== $value;
				};
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_GREATER) {
			$predicate = function($property, $value) {
				return $property > $value;
			};
		} elseif ($operator === ConditionParserHelper::OPERATOR_EQUAL_OR_GREATER) {
			$predicate = function($property, $value) {
				return $property >= $value;
			};
		} elseif ($operator === ConditionParserHelper::OPERATOR_SMALLER) {
			$predicate = function($property, $value) {
				return $property < $value;
			};
		} elseif ($operator === ConditionParserHelper::OPERATOR_EQUAL_OR_SMALLER) {
			$predicate = function($property, $value) {
				return $property <= $value;
			};
		} else {
			throw new InvalidArgumentException();
		}

		return $this->createFilterEvaluator($chain, $predicate, $sourceEntityMeta, $value);
	}


	protected function createFilterEvaluator($chainSource, Closure $predicate, EntityMetadata $sourceEntityMetaSource, $targetValue)
	{
		$evaluator = function($element, $chain = NULL, EntityMetadata $sourceEntityMeta = NULL)
		             use (& $evaluator, $predicate, $chainSource, $sourceEntityMetaSource, $targetValue)
		{
			if (!$chain) {
				$sourceEntityMeta = $sourceEntityMetaSource;
				$chain = $chainSource;
			}

			$column = array_shift($chain);
			$propertyMeta = $sourceEntityMeta->getProperty($column); // check if property exists
			$value = $element->$column;

			if (!$chain) {
				if ($column === 'id' && count($sourceEntityMeta->getPrimaryKey()) > 1 && !isset($targetValue[0][0])) {
					$targetValue = [$targetValue];
				}
				return $predicate($value instanceof IEntity ? $value->id : $value, $targetValue);
			}

			$targetEntityMeta = $this->metadataStorage->get($propertyMeta->relationship->entity);
			if ($value === NULL) {
				return FALSE;

			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value as $node) {
					if ($evaluator($node, $chain, $targetEntityMeta)) {
						return TRUE;
					}
				}

				return FALSE;
			} else {
				return $evaluator($value, $chain, $targetEntityMeta);
			}
		};

		return $evaluator;
	}


	/**
	 * @param  string $condition
	 * @param  string $direction
	 * @return Closure
	 */
	public function createSorter(array $conditions)
	{
		$columns = [];
		foreach ($conditions as $pair) {
			list($column, , $sourceEntity) = ConditionParserHelper::parseCondition($pair[0]);
			$sourceEntityMeta = $this->metadataStorage->get($sourceEntity ?: $this->mapper->getRepository()->getEntityClassNames()[0]);
			$columns[] = [$column, $pair[1], $sourceEntityMeta];
		}

		$getter = function($element, $chain, EntityMetadata $sourceEntityMeta) use (& $getter) {
			$column = array_shift($chain);
			$propertyMeta = $sourceEntityMeta->getProperty($column); // check if property exists
			$value = $element->$column;

			if ($value instanceof IRelationshipCollection) {
				throw new InvalidStateException('You can not sort by hasMany relationship.');
			}

			if (!$chain) {
				return $value;
			} else {
				$targetEntityMeta = $this->metadataStorage->get($propertyMeta->relationship->entity);
				return $getter($value, $chain, $targetEntityMeta);
			}
		};

		return function ($a, $b) use ($getter, $columns) {
			foreach ($columns as $pair) {
				$_a = $this->simplifyValue($getter, $a, $pair);
				$_b = $this->simplifyValue($getter, $b, $pair);
				$direction = $pair[1] === ICollection::ASC ? 1 : -1;

				if (is_int($_a) || is_float($_a)) {
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

	private function simplifyValue($getter, $raw, array $pair)
	{
		$value = $getter($raw, $pair[0], $pair[2]);
		if ($value instanceof IEntity) {
			return $value->getValue('id');

		} elseif ($value instanceof \DateTime) {
			return $value->format('%U.%u');
		}

		return $value;
	}

}

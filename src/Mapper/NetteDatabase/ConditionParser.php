<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\NetteDatabase;

use Nette\Object;
use Nextras\Orm\Entity\Collection\ConditionParser as CollectionConditionParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\StorageReflection\IDbStorageReflection;
use Nextras\Orm\InvalidArgumentException;


/**
 * ConditionParser for Nette\Database.
 */
class ConditionParser extends Object
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
	 * Transforms orm condition to sql expression for Nette Database.
	 * @param  string
	 * @return string
	 */
	public function parse($condition)
	{
		$chain = CollectionConditionParser::parseCondition($condition);
		if (count($chain) === 1) {
			return $this->mapper->getStorageReflection()->convertEntityToStorageKey($chain[1]);
		}

		return $this->parseCondition($chain, $this->mapper);
	}


	private function parseCondition(array $levels, IMapper $mapper)
	{
		/** @var IDbStorageReflection $reflection */
		$reflection = $mapper->getStorageReflection();
		$expression = '';
		$column     = array_pop($levels);
		$entityMD   = $this->metadataStorage->get($mapper->getRepository()->getEntityClassNames()[0]);

		foreach ($levels as $level) {

			if (!$entityMD->hasProperty($level)) {
				throw new InvalidArgumentException("Unknown property '$level' for '{$entityMD->entityClass}' entity."); // todo: better message
			}

			$propertyMD = $entityMD->getProperty($level);
			if (!$propertyMD->relationshipRepository) {
				throw new InvalidArgumentException("Entity '{$entityMD->entityClass}' does not have relationship in '$level'."); // todo: better message
			}


			$targetMapper     = $this->model->getRepository($propertyMD->relationshipRepository)->getMapper();
			$targetReflection = $targetMapper->getStorageReflection();

			if ($propertyMD->relationshipType === PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY) {
				$table = $targetReflection->getStorageName();
				$joinColumn = $targetReflection->convertEntityToStorageKey($propertyMD->relationshipProperty);
				$expression .= ":{$table}({$joinColumn})";

			} elseif ($propertyMD->relationshipType === PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY) {
				if ($propertyMD->relationshipIsMain) {
					$expression .= ':' . $reflection->getManyHasManyStorageName($targetMapper);
					$expression .= '.' . $reflection->getManyHasManyStoragePrimaryKeys($targetMapper)[1];

				} else {
					$expression .= ':' . $targetReflection->getManyHasManyStorageName($mapper);
					$expression .= '.' . $targetReflection->getManyHasManyStoragePrimaryKeys($mapper)[0];
				}

			} else {
				$expression .= '.' . $reflection->convertEntityToStorageKey($level);
			}

			$mapper     = $targetMapper;
			$reflection = $targetReflection;
			$entityMD   = $this->metadataStorage->get($mapper->getRepository()->getEntityClassNames()[0]);
		}

		// check if property exists
		$entityMD->getProperty($column);
		$column = $reflection->convertEntityToStorageKey($column);
		return "{$expression}.{$column}";
	}

}

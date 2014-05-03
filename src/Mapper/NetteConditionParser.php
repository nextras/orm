<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper;

use Nette\Object;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\StorageReflection\IDbStorageReflection;
use Nextras\Orm\InvalidArgumentException;


class NetteConditionParser extends Object
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
	 * @param  string
	 * @return string
	 */
	public function parse($condition)
	{
		if (!preg_match('#^(this((?:->\w+)+)\.(\w+)|\w+)$#', $condition)) {
			throw new InvalidArgumentException('Unsupported condition format');
		}

		if (preg_match('#^\w+$#', $condition)) {
			return $this->mapper->getStorageReflection()->convertEntityToStorageKey($condition);
		}

		return preg_replace_callback('#this((?:->\w+)+)\.(\w+)#i', function($matches) {
			$levels = explode('->', substr($matches[1], 2));
			return $this->parseCondition($levels, $matches[2], $this->mapper);
		}, $condition);
	}


	private function parseCondition(array $levels, $column, IMapper $mapper)
	{
		/** @var IDbStorageReflection $reflection */
		$reflection = $mapper->getStorageReflection();
		$expression = '';
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

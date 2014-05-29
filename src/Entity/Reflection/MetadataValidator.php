<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\Object;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IModel;


class MetadataValidator extends Object
{

	/**
	 * @param EntityMetadata[]
	 * @param IModel
	 */
	public function validate(array $metadata, IModel $model)
	{
		foreach ($metadata as $entityMeta) {
			foreach ($entityMeta->getProperties() as $propertyMeta) {
				if (!$propertyMeta->relationshipType) continue;

				$repositoryName = $propertyMeta->relationshipRepository;
				if (!$model->hasRepository($repositoryName)) {
					throw new InvalidStateException("{$entityMeta->entityClass}::\${$propertyMeta->name} points to unknown '{$propertyMeta->relationshipRepository}' repository.");
				}

				$symetricEntityMeta = $metadata[$repositoryName::getEntityClassNames()[0]];

				if (!$symetricEntityMeta->hasProperty($propertyMeta->relationshipProperty)) {
					throw new InvalidStateException("{$symetricEntityMeta->entityClass}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->entityClass}::\${$propertyMeta->relationshipProperty}.");
				}

				if ($propertyMeta->relationshipType === PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY) {
					$symetricPropertyMeta = $symetricEntityMeta->getProperty($propertyMeta->relationshipProperty);
					if ($propertyMeta->relationshipIsMain && $symetricPropertyMeta->relationshipIsMain) {
						throw new InvalidStateException("Only one side of relationship {$symetricEntityMeta->entityClass}::\${$propertyMeta->name} × {$symetricEntityMeta->entityClass}::\${$propertyMeta->relationshipProperty} could be defined as a primary.");
					} elseif (!$propertyMeta->relationshipIsMain && !$symetricPropertyMeta->relationshipIsMain) {
						throw new InvalidStateException("At least one side of relationship {$symetricEntityMeta->entityClass}::\${$propertyMeta->name} × {$symetricEntityMeta->entityClass}::\${$propertyMeta->relationshipProperty} has to be defined as a primary.");
					}
				}
			}
		}
	}

}

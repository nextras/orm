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
use Nextras\Orm\Model\IRepositoryLoader;


class MetadataValidator extends Object
{

	/**
	 * @param EntityMetadata[]  $metadata
	 * @param IRepositoryLoader $repositoryLoader
	 */
	public function validate(array $metadata, IRepositoryLoader $repositoryLoader)
	{
		$pairs = [
			PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY => PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY,
			PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE => PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY,
			PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY => PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE,
			PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE => PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE,
			PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED => PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED,
		];

		foreach ($metadata as $entityMeta) {
			foreach ($entityMeta->getProperties() as $propertyMeta) {
				if (!$propertyMeta->relationshipType) continue;

				$repositoryName = $propertyMeta->relationshipRepository;
				if (!$repositoryLoader->hasRepository($repositoryName)) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} points to unknown '{$propertyMeta->relationshipRepository}' repository.");
				}

				$symetricEntityMeta = $metadata[$repositoryName::getEntityClassNames()[0]];

				if (!$symetricEntityMeta->hasProperty($propertyMeta->relationshipProperty)) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->className}::\${$propertyMeta->relationshipProperty}.");
				}

				/** @var PropertyMetadata $symetricPropertyMeta */
				$symetricPropertyMeta = $symetricEntityMeta->getProperty($propertyMeta->relationshipProperty);
				if ($propertyMeta->name !== $symetricPropertyMeta->relationshipProperty) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} relationship with {$symetricEntityMeta->className}::\${$propertyMeta->relationshipProperty} is not symetric.");
				}

				if ($symetricPropertyMeta->relationshipType === NULL) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->className}::\${$propertyMeta->relationshipProperty}.");
				}

				if ($symetricPropertyMeta->relationshipType !== $pairs[$propertyMeta->relationshipType]) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a propper reverse relationship type in {$symetricEntityMeta->className}::\${$propertyMeta->relationshipProperty}.");
				}

				if ($propertyMeta->relationshipType === PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY || $propertyMeta->relationshipType === PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED) {
					if ($propertyMeta->relationshipIsMain && $symetricPropertyMeta->relationshipIsMain) {
						throw new InvalidStateException("Only one side of relationship {$entityMeta->className}::\${$propertyMeta->name} × {$symetricEntityMeta->className}::\${$propertyMeta->relationshipProperty} could be defined as a primary.");
					} elseif (!$propertyMeta->relationshipIsMain && !$symetricPropertyMeta->relationshipIsMain) {
						throw new InvalidStateException("At least one side of relationship {$entityMeta->className}::\${$propertyMeta->name} × {$symetricEntityMeta->className}::\${$propertyMeta->relationshipProperty} has to be defined as a primary.");
					}
				}
			}
		}
	}

}

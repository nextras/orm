<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
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
			PropertyRelationshipMetadata::MANY_HAS_MANY => PropertyRelationshipMetadata::MANY_HAS_MANY,
			PropertyRelationshipMetadata::MANY_HAS_ONE => PropertyRelationshipMetadata::ONE_HAS_MANY,
			PropertyRelationshipMetadata::ONE_HAS_MANY => PropertyRelationshipMetadata::MANY_HAS_ONE,
			PropertyRelationshipMetadata::ONE_HAS_ONE => PropertyRelationshipMetadata::ONE_HAS_ONE,
			PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED => PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED,
		];

		foreach ($metadata as $entityMeta) {
			foreach ($entityMeta->getProperties() as $propertyMeta) {
				if (!$propertyMeta->relationship) {
					continue;
				}

				$repositoryName = $propertyMeta->relationship->repository;
				if (!$repositoryLoader->hasRepository($repositoryName)) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} points to unknown '{$propertyMeta->relationship->repository}' repository.");
				}

				$symetricEntityMeta = $metadata[$propertyMeta->relationship->entity];

				if (!$symetricEntityMeta->hasProperty($propertyMeta->relationship->property)) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				/** @var PropertyMetadata $symetricPropertyMeta */
				$symetricPropertyMeta = $symetricEntityMeta->getProperty($propertyMeta->relationship->property);
				if ($symetricPropertyMeta->relationship === NULL) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				if ($propertyMeta->name !== $symetricPropertyMeta->relationship->property) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} relationship with {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property} is not symetric.");
				}

				if ($symetricPropertyMeta->relationship->type !== $pairs[$propertyMeta->relationship->type]) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a propper reverse relationship type in {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				if ($propertyMeta->relationship->type === PropertyRelationshipMetadata::MANY_HAS_MANY || $propertyMeta->relationship->type === PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED) {
					if ($propertyMeta->relationship->isMain && $symetricPropertyMeta->relationship->isMain) {
						throw new InvalidStateException("Only one side of relationship {$entityMeta->className}::\${$propertyMeta->name} × {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property} could be defined as a primary.");
					} elseif (!$propertyMeta->relationship->isMain && !$symetricPropertyMeta->relationship->isMain) {
						throw new InvalidStateException("At least one side of relationship {$entityMeta->className}::\${$propertyMeta->name} × {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property} has to be defined as a primary.");
					}
				}
			}
		}
	}

}

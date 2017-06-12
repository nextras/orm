<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IRepositoryLoader;


class MetadataValidator
{
	/**
	 * @param EntityMetadata[]  $metadata
	 */
	public function validate(array $metadata, IRepositoryLoader $repositoryLoader)
	{
		$pairs = [
			PropertyRelationshipMetadata::MANY_HAS_MANY => PropertyRelationshipMetadata::MANY_HAS_MANY,
			PropertyRelationshipMetadata::MANY_HAS_ONE => PropertyRelationshipMetadata::ONE_HAS_MANY,
			PropertyRelationshipMetadata::ONE_HAS_MANY => PropertyRelationshipMetadata::MANY_HAS_ONE,
			PropertyRelationshipMetadata::ONE_HAS_ONE => PropertyRelationshipMetadata::ONE_HAS_ONE,
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

				if ($propertyMeta->relationship->property === null) {
					$relType = $propertyMeta->relationship->type;
					$isAllowedOneSided
						= ($relType === PropertyRelationshipMetadata::ONE_HAS_ONE && $propertyMeta->relationship->isMain)
						|| ($relType === PropertyRelationshipMetadata::MANY_HAS_ONE)
						|| ($relType === PropertyRelationshipMetadata::MANY_HAS_MANY && $propertyMeta->relationship->isMain);
					if (!$isAllowedOneSided) {
						throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} must have defined a symetric relationship.");
					} else {
						continue;
					}
				}

				$symetricEntityMeta = $metadata[$propertyMeta->relationship->entity];

				if (!$symetricEntityMeta->hasProperty($propertyMeta->relationship->property)) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				/** @var PropertyMetadata $symetricPropertyMeta */
				$symetricPropertyMeta = $symetricEntityMeta->getProperty($propertyMeta->relationship->property);
				if ($symetricPropertyMeta->relationship === null) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symetric relationship in {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				if ($propertyMeta->name !== $symetricPropertyMeta->relationship->property) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} relationship with {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property} is not symetric.");
				}

				if ($symetricPropertyMeta->relationship->type !== $pairs[$propertyMeta->relationship->type]) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a proper reverse relationship type in {$symetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				if ($propertyMeta->relationship->type === PropertyRelationshipMetadata::MANY_HAS_MANY || $propertyMeta->relationship->type === PropertyRelationshipMetadata::ONE_HAS_ONE) {
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

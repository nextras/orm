<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IRepositoryLoader;


class MetadataValidator
{
	/**
	 * @param EntityMetadata[] $metadata
	 * @throws InvalidStateException
	 */
	public function validate(array $metadata, IRepositoryLoader $repositoryLoader): void
	{
		$pairs = [
			PropertyRelationshipMetadata::MANY_HAS_MANY => PropertyRelationshipMetadata::MANY_HAS_MANY,
			PropertyRelationshipMetadata::MANY_HAS_ONE => PropertyRelationshipMetadata::ONE_HAS_MANY,
			PropertyRelationshipMetadata::ONE_HAS_MANY => PropertyRelationshipMetadata::MANY_HAS_ONE,
			PropertyRelationshipMetadata::ONE_HAS_ONE => PropertyRelationshipMetadata::ONE_HAS_ONE,
		];

		foreach ($metadata as $entityMeta) {
			foreach ($entityMeta->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship === null) {
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
						throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} must have defined a symmetric relationship.");
					} else {
						continue;
					}
				}

				$symmetricEntityMeta = $metadata[$propertyMeta->relationship->entity];

				if (!$symmetricEntityMeta->hasProperty($propertyMeta->relationship->property)) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symmetric relationship in {$symmetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				/** @var PropertyMetadata $symmetricPropertyMeta */
				$symmetricPropertyMeta = $symmetricEntityMeta->getProperty($propertyMeta->relationship->property);
				if ($symmetricPropertyMeta->relationship === null) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a symmetric relationship in {$symmetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				if ($propertyMeta->name !== $symmetricPropertyMeta->relationship->property) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} relationship with {$symmetricEntityMeta->className}::\${$propertyMeta->relationship->property} is not symmetric.");
				}

				if ($symmetricPropertyMeta->relationship->type !== $pairs[$propertyMeta->relationship->type]) {
					throw new InvalidStateException("{$entityMeta->className}::\${$propertyMeta->name} has not defined a proper reverse relationship type in {$symmetricEntityMeta->className}::\${$propertyMeta->relationship->property}.");
				}

				if ($propertyMeta->relationship->type === PropertyRelationshipMetadata::MANY_HAS_MANY || $propertyMeta->relationship->type === PropertyRelationshipMetadata::ONE_HAS_ONE) {
					if ($propertyMeta->relationship->isMain && $symmetricPropertyMeta->relationship->isMain) {
						throw new InvalidStateException("Only one side of relationship {$entityMeta->className}::\${$propertyMeta->name} × {$symmetricEntityMeta->className}::\${$propertyMeta->relationship->property} could be defined as a main.");
					} elseif (!$propertyMeta->relationship->isMain && !$symmetricPropertyMeta->relationship->isMain) {
						throw new InvalidStateException("At least one side of relationship {$entityMeta->className}::\${$propertyMeta->name} × {$symmetricEntityMeta->className}::\${$propertyMeta->relationship->property} has to be defined as a main.");
					}
				}
			}
		}
	}
}

<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;


/**
 * Property that requires entity & nesting property path hierarchy to work correctly.
 */
interface IEntityAwareProperty extends IProperty
{
	/**
	 * Attaches entity to the property.
	 *
	 * Passed property metadata is properly configured in context of entity/embeddable nesting property path hierarchy.
	 * If you need access metadata before property is attached, use the constructor passed metadata. The codee should
	 * throw whe the hierarchy is needed before this attachment.
	 *
	 * @internal
	 * @ignore
	 */
	public function onAttach(IEntity $entity, PropertyMetadata $propertyMetadata): void;
}

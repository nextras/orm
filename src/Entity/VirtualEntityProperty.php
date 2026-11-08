<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Attribute;


/**
 * Registers a public virtual property with native PHP hooks in entity metadata.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class VirtualEntityProperty
{
}

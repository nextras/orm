<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property Identifier           $id        {primary} {wrapper IdentifierWrapper}
 * @property string               $name
 * @property OneHasMany<Document> $documents {1:m Document::$folder}
 */
final class Folder extends Entity
{
}

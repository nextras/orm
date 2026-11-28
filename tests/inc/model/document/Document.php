<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;


/**
 * @property Identifier $id     {primary} {wrapper IdentifierWrapper}
 * @property string     $name
 * @property Folder     $folder {m:1 Folder::$documents}
 */
final class Document extends Entity
{
}

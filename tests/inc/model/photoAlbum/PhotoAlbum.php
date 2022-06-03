<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property int|null          $id      {primary}
 * @property string            $title
 * @property OneHasMany<Photo> $photos  {1:m Photo::$album}
 * @property Photo|null        $preview {1:1 Photo::$previewFor, isMain=true}
 */
final class PhotoAlbum extends Entity
{
}

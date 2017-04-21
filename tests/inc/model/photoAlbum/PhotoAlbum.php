<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property int         $id      {primary}
 * @property string      $title
 * @property Photo[]|OHM $photos  {1:m Photo::$album}
 * @property Photo|null  $preview {1:1 Photo::$previewFor, isMain=true}
 */
final class PhotoAlbum extends Entity
{
}

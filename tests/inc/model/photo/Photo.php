<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property int             $id         {primary}
 * @property string          $title
 * @property PhotoAlbum      $album      {m:1 PhotoAlbum::$photos}
 * @property PhotoAlbum|null $previewFor {1:1 PhotoAlbum::$preview}
 */
final class Photo extends Entity
{
}

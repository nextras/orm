<?php

namespace NextrasTests\Orm;

use DateTime;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany as MHM;


/**
 * @property string             $title
 * @property DateTime           $createdAt     {default now}
 * @property Author             $author        {m:1 Author}
 * @property Author|NULL        $translator    {m:1 Author::$translatedBooks}
 * @property MHM|Tag[]          $tags          {m:n Tag primary}
 * @property Book|NULL          $nextPart      {1:1d Book::$previousPart primary}
 * @property Book|NULL          $previousPart  {1:1d Book::$nextPart}
 * @property Ean|NULL           $ean           {1:1d Ean primary}
 * @property Publisher          $publisher     {m:1 PublishersRepository}
 */
final class Book extends Entity
{

}

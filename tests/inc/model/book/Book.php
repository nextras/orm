<?php

namespace NextrasTests\Orm;

use DateTime;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany as MHM;


/**
 * @property string             $title
 * @property Author             $author        {m:1 Author::$books}
 * @property Author|NULL        $translator    {m:1 Author::$translatedBooks}
 * @property MHM|Tag[]          $tags          {m:n Tag::$books, primary=true}
 * @property Book|NULL          $nextPart      {1:1d Book::$previousPart, primary=true}
 * @property Book|NULL          $previousPart  {1:1d Book::$nextPart}
 * @property Ean|NULL           $ean           {1:1d Ean::$book, primary=true}
 * @property Publisher          $publisher     {m:1 Publisher::$books}
 * @property DateTime           $publishedAt   {default now}
 */
final class Book extends Entity
{
}

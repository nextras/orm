<?php

namespace NextrasTests\Orm;

use DateTime;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property int                $id                {primary}
 * @property string             $name
 * @property DateTime|NULL      $born              {default now}
 * @property string             $web               {default "http://www.example.com"}
 * @property OHM|Book[]         $books             {1:m Book::$author, orderBy=[id, DESC], cascade=[persist, remove]}
 * @property OHM|Book[]         $translatedBooks   {1:m Book::$translator}
 * @property OHM|TagFollower[]  $tagFollowers      {1:m TagFollower::$author, cascade=[persist, remove]}
 *
 * @property-read int           $age               {virtual}
 */
final class Author extends Entity
{
	protected function getterAge()
	{
		if (!$this->born) {
			return 0;
		}

		return date('Y') - $this->born->format('Y');
	}
}

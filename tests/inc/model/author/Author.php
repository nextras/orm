<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property string $name
 * @property DateTime|NULL $born {default now}
 * @property string $web {default http://www.example.com}
 * @property OneHasMany|Book[] $books {1:m BooksRepository order:id,DESC}
 * @property OneHasMany|Book[] $translatedBooks {1:m BooksRepository $translator}
 * @property OneHasMany|TagFollower[] $tagFollowers {1:m TagFollowersRepository}
 * @property-read int $age
 */
final class Author extends Entity
{

	public function getAge()
	{
		if (!$this->born) {
			return 0;
		}

		return date('Y') - $this->born->format('Y');
	}

}

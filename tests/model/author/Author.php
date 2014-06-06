<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property string $web
 * @property OneHasMany|Book[] $books {1:m BooksRepository}
 * @property OneHasMany|Book[] $translatedBooks {1:m BooksRepository $translator}
 * @property-read int $age
 */
final class Author extends Person
{

	public function getAge()
	{
		if (!$this->born) {
			return 0;
		}

		return date('Y') - $this->born->format('Y');
	}

}

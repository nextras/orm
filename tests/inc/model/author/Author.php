<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\PropertyWrapper\DateWrapper;
use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property int|null                $id              {primary}
 * @property string                  $name
 * @property DateTimeImmutable|null  $bornOn          {default "2021-03-21"} {wrapper DateWrapper}
 * @property string                  $web             {default "http://www.example.com"}
 * @property Author|null             $favoriteAuthor  {m:1 Author::$favoredBy}
 * @property OneHasMany<Author>      $favoredBy       {1:m Author::$favoriteAuthor}
 * @property OneHasMany<Book>        $books           {1:m Book::$author, orderBy=[id=DESC], cascade=[persist, remove, removeOrphan]}
 * @property OneHasMany<Book>        $translatedBooks {1:m Book::$translator}
 * @property OneHasMany<TagFollower> $tagFollowers    {1:m TagFollower::$author, cascade=[persist, remove, removeOrphan]}
 * @property-read int                $age             {virtual}
 */
final class Author extends Entity
{
	protected function getterAge(): int
	{
		if ($this->bornOn === null) {
			return 0;
		}

		return ((int) date('Y')) - ((int) $this->bornOn->format('Y'));
	}
}

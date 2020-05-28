<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Entity;


/**
 * @property-read int|null $id                            {primary}
 * @property-read string $name
 * @property-read ICollection|Book[] $books               {m:m Book::$tags, exposeCollection=true}
 * @property-read ICollection|TagFollower[] $tagFollowers {1:m TagFollower::$tag, cascade=[persist, remove], exposeCollection=true}
 * @property-read bool $isGlobal                          {default true}
 */
final class Tag extends Entity
{
	public function __construct($name = null)
	{
		parent::__construct();
		if ($name !== null) {
			$this->setName($name);
		}
	}


	public function setName(string $name): void
	{
		$this->setReadOnlyValue('name', $name);
	}


	public function setBooks(Book ...$books): void
	{
		$this->getProperty('books')->set($books);
	}
}

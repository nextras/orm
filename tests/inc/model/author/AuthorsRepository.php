<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Author>
 */
final class AuthorsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Author::class];
	}


	/**
	 * @return Author[]|ICollection<Author>
	 */
	public function findByTags(string $name): ICollection
	{
		return $this->findBy(['books->tags->name' => $name]);
	}
}

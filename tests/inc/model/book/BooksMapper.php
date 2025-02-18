<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;


/**
 * @extends DbalMapper<Book>
 */
final class BooksMapper extends DbalMapper
{
	/** @return ICollection<Book> */
	public function findBooksWithEvenId(): ICollection
	{
		return $this->toCollection($this->builder()->where('id % 2 = 0'));
	}


	public function findFirstBook(): ?Book
	{
		return $this->toEntity($this->builder()->where('id = 1'));
	}


	protected function createConventions(): IConventions
	{
		$reflection = parent::createConventions();
		$reflection->setMapping('price->cents', 'price');
		return $reflection;
	}
}

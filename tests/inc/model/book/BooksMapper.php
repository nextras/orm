<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Mapper;


final class BooksMapper extends Mapper
{
	/** @return Book[]|ICollection */
	public function findBooksWithEvenId(): ICollection
	{
		return $this->toCollection($this->builder()->where('id % 2 = 0'));
	}


	/** @return Book|null */
	public function findFirstBook(): ?IEntity
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

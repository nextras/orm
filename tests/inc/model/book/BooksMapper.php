<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Mapper\Mapper;


final class BooksMapper extends Mapper
{
	public function findBooksWithEvenId()
	{
		return $this->builder()->where('id % 2 = 0');
	}
}

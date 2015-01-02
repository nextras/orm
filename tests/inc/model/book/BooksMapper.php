<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Mapper\Mapper;


final class BooksMapper extends Mapper
{

	public function findBooksWithEvenId()
	{
		return $this->table()->where('id % 2 = 0');
	}

}

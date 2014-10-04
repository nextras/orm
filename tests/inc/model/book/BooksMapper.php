<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Mapper\Mapper;


final class BooksMapper extends Mapper
{

	public function getBooksWithEvenId()
	{
		return $this->table()->where('id % 2 = 0');
	}

}

<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Closure;
use Nette\Utils\Strings;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Mapper\Mapper;


final class BooksMapper extends Mapper
{
	public function findBooksWithEvenId(): ICollection
	{
		return $this->toCollection($this->builder()->where('id % 2 = 0'));
	}


	public function findFirstBook()
	{
		return $this->toEntity($this->builder()->where('id = 1'));
	}



	//public function processQueryBuilderFunctionBooksTagLimit(QueryBuilder $builder, int $minCount): array
	//{
	//	$builder->leftJoin('books', '[books_x_tags]', 'bxt', '[bxt.book_id] = [books.id]');
	//	$builder->groupBy('[books.id]');
	//	$builder->having('count([bxt.tag_id]) >= %i', $minCount);
	//	return [];
	//}
}

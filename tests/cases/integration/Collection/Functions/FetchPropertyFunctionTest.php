<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection\Functions;


use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Orm\Collection\Aggregations\AnyAggregator;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\FetchPropertyFunction;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\Helper;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


class FetchPropertyFunctionTest extends DataTestCase
{
	public function testManyHasOneJoin(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) $this->skip();
		/** @var DbalMapper<Book> $mapper */
		$mapper = $this->orm->books->getMapper();
		$builder = $mapper->builder();
		$helper = new DbalQueryBuilderHelper($this->orm->books);
		$function = new FetchPropertyFunction($this->orm->books, $this->orm->books->getMapper(), $this->orm);

		$expression = $function->processDbalExpression(
			$helper,
			$builder,
			['author->name'],
			ExpressionContext::FilterAnd,
		);
		Assert::count(0, $expression->groupBy);
		Assert::count(1, $expression->joins);
		Assert::equal(new Fqn('author', 'id'), $expression->joins[0]->toPrimaryKey);
		Assert::count(1, $expression->columns);
		Assert::equal(new Fqn('author', 'name'), $expression->columns[0]);
	}


	public function testOneHasManyJoin(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) $this->skip();
		/** @var DbalMapper<Author> $mapper */
		$mapper = $this->orm->authors->getMapper();
		$builder = $mapper->builder();
		$helper = new DbalQueryBuilderHelper($this->orm->authors);
		$function = new FetchPropertyFunction($this->orm->authors, $this->orm->authors->getMapper(), $this->orm);

		$expression = $function->processDbalExpression(
			$helper,
			$builder,
			['books->title'],
			ExpressionContext::FilterAnd,
		);
		if ($this->section === Helper::SECTION_MSSQL) {
			Assert::count(5, $expression->groupBy); // contains additional columns from SELECT clause
		} else {
			Assert::count(1, $expression->groupBy);
		}
		Assert::equal(new Fqn('authors', 'id'), $expression->groupBy[0]);
		Assert::count(1, $expression->joins);
		Assert::equal(new Fqn('books_any', 'id'), $expression->joins[0]->toPrimaryKey);
		Assert::count(1, $expression->columns);
		Assert::equal(new Fqn('books_any', 'title'), $expression->columns[0]);

		$expression = $function->processDbalExpression(
			$helper,
			$builder,
			['books->title'],
			ExpressionContext::FilterAnd,
			new AnyAggregator('any2'),
		);
		if ($this->section === Helper::SECTION_MSSQL) {
			Assert::count(5, $expression->groupBy); // contains additional columns from SELECT clause
		} else {
			Assert::count(1, $expression->groupBy);
		}
		Assert::equal(new Fqn('authors', 'id'), $expression->groupBy[0]);
		Assert::count(1, $expression->joins);
		Assert::equal(new Fqn('books_any2', 'id'), $expression->joins[0]->toPrimaryKey);
		Assert::count(1, $expression->columns);
		Assert::equal(new Fqn('books_any2', 'title'), $expression->columns[0]);
	}


	public function testOneHasOneJoin(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) $this->skip();
		/** @var DbalMapper<Ean> $mapper */
		$mapper = $this->orm->eans->getMapper();
		$builder = $mapper->builder();
		$helper = new DbalQueryBuilderHelper($this->orm->eans);
		$function = new FetchPropertyFunction($this->orm->eans, $this->orm->eans->getMapper(), $this->orm);

		$expression = $function->processDbalExpression(
			$helper,
			$builder,
			['book->title'],
			ExpressionContext::FilterAnd,
		);
		Assert::count(0, $expression->groupBy);
		Assert::count(1, $expression->joins);
		Assert::equal(new Fqn('book', 'id'), $expression->joins[0]->toPrimaryKey);
		Assert::count(1, $expression->columns);
		Assert::equal(new Fqn('book', 'title'), $expression->columns[0]);
	}
}


(new FetchPropertyFunctionTest())->run();

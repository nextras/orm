<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Orm\Collection\Expression\LikeExpression;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionLikeTest extends DataTestCase
{
	public function testFilterLike()
	{
		$count = $this->orm->books->findBy(['title~' => LikeExpression::raw('Book%')])->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::raw('Book 1%')])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::raw('Book X%')])->count();
		Assert::same(0, $count);
	}


	public function testFilterLikeCombined()
	{
		$count = $this->orm->books->findBy([
			ICollection::AND,
			['title~' => LikeExpression::raw('Book%')],
			['translator!=' => null],
		])->count();
		Assert::same(3, $count);

		$count = $this->orm->books->findBy([
			ICollection::OR,
			['title~' => LikeExpression::raw('Book 1%')],
			['translator' => null],
		])->count();
		Assert::same(2, $count);
	}


	public function testFilterLikePositions()
	{
		$count = $this->orm->books->findBy(['title~' => LikeExpression::startsWith('Book')])->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::startsWith('Book 1')])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::startsWith('Book X')])->count();
		Assert::same(0, $count);


		$count = $this->orm->books->findBy(['title~' => LikeExpression::endsWith('ook')])->count();
		Assert::same(0, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::endsWith('ook 1')])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::endsWith('ook X')])->count();
		Assert::same(0, $count);


		$count = $this->orm->books->findBy(['title~' => LikeExpression::contains('ook')])->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::contains('ook 1')])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy(['title~' => LikeExpression::contains('ook X')])->count();
		Assert::same(0, $count);
	}
}


$test = new CollectionLikeTest($dic);
$test->run();

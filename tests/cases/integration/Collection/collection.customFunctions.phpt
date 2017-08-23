<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\LikeFunction;
use NextrasTests\Orm\LikeFilterFunction;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionCustomFunctionsTest extends DataTestCase
{
	public function testFilterLike()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$count = $this->orm->books->findBy([LikeFilterFunction::class, 'title', 'Book'])->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findBy([LikeFilterFunction::class, 'title', 'Book 1'])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy([LikeFilterFunction::class, 'title', 'Book X'])->count();
		Assert::same(0, $count);
	}


	public function testLike()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$count = $this->orm->books->findAll()->applyFunction(LikeFunction::class, 'title', 'Book')->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findAll()->applyFunction(LikeFunction::class, 'title', 'Book 1')->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findAll()->applyFunction(LikeFunction::class, 'title', 'Book X')->count();
		Assert::same(0, $count);
	}



	public function testFilterLikeCombined()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$count = $this->orm->books->findBy([
			ICollection::AND,
			[LikeFilterFunction::class, 'title', 'Book'],
			['translator!=' => null],
		])->count();
		Assert::same(3, $count);


		$count = $this->orm->books->findBy([
			ICollection::OR,
			[LikeFilterFunction::class, 'title', 'Book 1'],
			['translator' => null],
		])->count();
		Assert::same(2, $count);
	}


	public function testLikeArray()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$collection = new ArrayCollection(iterator_to_array($this->orm->books->findAll()), $this->orm->books);

		$count = $collection->applyFunction(LikeFunction::class, 'title', 'Book')->count();
		Assert::same(4, $count);

		$count = $collection->applyFunction(LikeFunction::class, 'title', 'Book 1')->count();
		Assert::same(1, $count);

		$count = $collection->applyFunction(LikeFunction::class, 'title', 'Book X')->count();
		Assert::same(0, $count);
	}


	public function testFilterLikeArray()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$collection = new ArrayCollection(iterator_to_array($this->orm->books->findAll()), $this->orm->books);

		$count = $collection->findBy([LikeFilterFunction::class, 'title', 'Book'])->count();
		Assert::same(4, $count);

		$count = $collection->findBy([LikeFilterFunction::class, 'title', 'Book 1'])->count();
		Assert::same(1, $count);

		$count = $collection->findBy([LikeFilterFunction::class, 'title', 'Book X'])->count();
		Assert::same(0, $count);
	}


	public function testFilterLikeArrayCombined()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$collection = new ArrayCollection(iterator_to_array($this->orm->books->findAll()), $this->orm->books);

		$count = $collection->findBy([
			ICollection::AND,
			[LikeFilterFunction::class, 'title', 'Book'],
			['translator!=' => null],
		])->count();
		Assert::same(3, $count);


		$count = $collection->findBy([
			ICollection::OR,
			[LikeFilterFunction::class, 'title', 'Book 1'],
			['translator' => null],
		])->count();
		Assert::same(2, $count);
	}

//	public function testTagLimit()
//	{
//		if ($this->section === Helper::SECTION_ARRAY) {
//			Environment::skip('Test only DbalMapper');
//		}
//
//		$count = $this->orm->books->findBy([CustomFunctions::BOOKS_TAG_LIMIT, 1])->count();
//		Assert::same(3, $count);
//
//		$count = $this->orm->books->findBy([CustomFunctions::BOOKS_TAG_LIMIT, 2])->count();
//		Assert::same(2, $count);
//
//		$count = $this->orm->books->findBy([CustomFunctions::BOOKS_TAG_LIMIT, 3])->count();
//		Assert::same(0, $count);
//	}
}


$test = new CollectionCustomFunctionsTest($dic);
$test->run();

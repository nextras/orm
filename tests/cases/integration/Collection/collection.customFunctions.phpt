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

		$count = $this->orm->books->findBy([LikeFunction::class, 'title', 'Book'])->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findBy([LikeFunction::class, 'title', 'Book 1'])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy([LikeFunction::class, 'title', 'Book X'])->count();
		Assert::same(0, $count);
	}


	public function testFilterLikeCombined()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$count = $this->orm->books->findBy([
			ICollection::AND,
			[LikeFunction::class, 'title', 'Book'],
			['translator!=' => null],
		])->count();
		Assert::same(3, $count);


		$count = $this->orm->books->findBy([
			ICollection::OR,
			[LikeFunction::class, 'title', 'Book 1'],
			['translator' => null],
		])->count();
		Assert::same(2, $count);
	}


	public function testFilterLikeArray()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$collection = new ArrayCollection(iterator_to_array($this->orm->books->findAll()), $this->orm->books);

		$count = $collection->findBy([LikeFunction::class, 'title', 'Book'])->count();
		Assert::same(4, $count);

		$count = $collection->findBy([LikeFunction::class, 'title', 'Book 1'])->count();
		Assert::same(1, $count);

		$count = $collection->findBy([LikeFunction::class, 'title', 'Book X'])->count();
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
			[LikeFunction::class, 'title', 'Book'],
			['translator!=' => null],
		])->count();
		Assert::same(3, $count);


		$count = $collection->findBy([
			ICollection::OR,
			[LikeFunction::class, 'title', 'Book 1'],
			['translator' => null],
		])->count();
		Assert::same(2, $count);
	}
}


$test = new CollectionCustomFunctionsTest($dic);
$test->run();

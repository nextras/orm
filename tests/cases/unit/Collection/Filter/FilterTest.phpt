<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Collection\Filter;

use Nextras\Orm\Collection\Filter\Filter;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @testCase
 */
final class FilterTest extends TestCase
{

	public function testDefault(): void
	{
		$filter = new Filter();

		Assert::same([], $filter->find()->getConditions());
		Assert::same([], $filter->order()->getOrder());
		Assert::same([null, null], $filter->getLimit());
	}

	public function testLimit(): void
	{
		$filter = new Filter();

		Assert::same([null, null], $filter->getLimit());

		$filter->limit(10, 5);
		Assert::same([10, 5], $filter->getLimit());

		$filter->limit(10);
		Assert::same([10, null], $filter->getLimit());
	}

}


$test = new FilterTest();
$test->run();

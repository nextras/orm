<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Collection\Filter;

use Nextras\Orm\Collection\Filter\OrderFilter;
use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\Functions\SumAggregateFunction;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @testCase
 */
final class OrderFilterTest extends TestCase
{

	public function testNoOrdering(): void
	{
		$order = new OrderFilter();

		Assert::same(
			[],
			$order->getOrder()
		);
	}

	public function testProperties(): void
	{
		$order = new OrderFilter();

		$order->property('property1');
		$order->property('property2', ICollection::DESC);

		Assert::same(
			[
				['property1', ICollection::ASC],
				['property2', ICollection::DESC],
			],
			$order->getOrder()
		);
	}

	public function testFunction(): void
	{
		$order = new OrderFilter();

		$f1 = $order->createFunction(CountAggregateFunction::class, 'property');
		$f2 = $order->createFunction(CompareEqualsFunction::class, 'property', 'val1', 'val2', 'val3');

		Assert::same(
			[
				CountAggregateFunction::class,
				'property',
			],
			$f1
		);

		Assert::same(
			[
				CompareEqualsFunction::class,
				'property',
				'val1',
				'val2',
				'val3',
			],
			$f2
		);

		$order->function(CompareEqualsFunction::class, 'property');
		$order->function(CompareGreaterThanFunction::class, $f1);
		$order->function(SumAggregateFunction::class, 'property', ICollection::DESC, 'val1', 'val2');

		Assert::same(
			[
				[
					[
						CompareEqualsFunction::class,
						'property',
					],
					ICollection::ASC,
				],
				[
					[
						CompareGreaterThanFunction::class,
						[
							CountAggregateFunction::class,
							'property',
						],
					],
					ICollection::ASC,
				],
				[
					[
						SumAggregateFunction::class,
						'property',
						'val1',
						'val2',
					],
					ICollection::DESC,
				],
			],
			$order->getOrder()
		);
	}

	public function testRaw(): void
	{
		$order = new OrderFilter();

		$order->raw('property1');
		$order->raw('property2', ICollection::DESC);
		$order->raw(['raw', 'array']);

		Assert::same(
			[
				['property1', ICollection::ASC],
				['property2', ICollection::DESC],
				[['raw', 'array'], ICollection::ASC],
			],
			$order->getOrder()
		);
	}

}

$test = new OrderFilterTest();
$test->run();

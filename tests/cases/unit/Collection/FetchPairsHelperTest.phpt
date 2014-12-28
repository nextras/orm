<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

use ArrayIterator;
use Nette\Utils\DateTime;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class FetchPairsHelperTest extends TestCase
{

	public function testParser()
	{
		$data = new ArrayIterator([
			$one = (object) ['name' => 'jon snow', 'email' => 'castleblack@wall.7k', 'born' => new DateTime('2014-01-01'), 'n' => 10],
			$two = (object) ['name' => 'oberyn martell', 'email' => 'ob@martell.7k', 'born' => new DateTime('2014-01-03'), 'n' => 12],
		]);

		Assert::same(
			['castleblack@wall.7k', 'ob@martell.7k'],
			FetchPairsHelper::process($data, NULL, 'email')
		);

		Assert::same(
			[10 => $one, 12 => $two],
			FetchPairsHelper::process($data, 'n')
		);

		Assert::same(
			[
				10 => 'castleblack@wall.7k',
				12 => 'ob@martell.7k',
			],
			FetchPairsHelper::process($data, 'n', 'email')
		);

		Assert::same(
			['2014-01-01 00:00:00' => $one, '2014-01-03 00:00:00' => $two],
			FetchPairsHelper::process($data, 'born')
		);

		Assert::same(
			[
				'2014-01-01 00:00:00' => 'castleblack@wall.7k',
				'2014-01-03 00:00:00' => 'ob@martell.7k',
			],
			FetchPairsHelper::process($data, 'born', 'email')
		);
	}


	public function testMissingArguments()
	{
		Assert::throws(function () {
			FetchPairsHelper::process(new ArrayIterator([]));
		}, 'Nextras\Orm\InvalidArgumentException', 'FetchPairsHelper requires defined key or value.');
	}
}


$test = new FetchPairsHelperTest($dic);
$test->run();

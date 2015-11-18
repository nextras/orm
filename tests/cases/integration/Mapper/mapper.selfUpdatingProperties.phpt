<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Doughnut;
use Tester\Assert;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MapperSelfUpdatingPropertiesTest extends DataTestCase
{

	private function skipUnsupported()
	{
		if (in_array($this->section, ['array', 'mysql'], TRUE)) {
			Environment::skip("RETURNING clause not supported by '{$this->section}'");
		}
	}

	public function testSelUpdate()
	{
		$this->skipUnsupported();

		$d = new Doughnut(2, 3);
		$this->orm->doughnuts->persistAndFlush($d);

		// dummy trigger sets computedProperty to $a * $b
		Assert::same(2*3, $d->computedProperty);

		$d->a = 4;
		Assert::same(2*3, $d->computedProperty);
		$this->orm->doughnuts->persistAndFlush($d);
		Assert::same(4*3, $d->computedProperty);
	}

}


$test = new MapperSelfUpdatingPropertiesTest($dic);
$test->run();

<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Tag;
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

		$tag = new Tag('A');
		$this->orm->tags->persistAndFlush($tag);

		// dummy trigger sets computedProperty to ascii value of $name[0]
		Assert::same(ord('A'), $tag->computedProperty);

		$tag->name = 'B';
		Assert::same(ord('A'), $tag->computedProperty);
		$this->orm->tags->persistAndFlush($tag);
		Assert::same(ord('B'), $tag->computedProperty);
	}

}


$test = new MapperSelfUpdatingPropertiesTest($dic);
$test->run();

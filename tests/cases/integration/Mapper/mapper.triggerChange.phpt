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


class MapperTriggerChangeTest extends DataTestCase
{

	protected function setUp()
	{
		parent::setUp();
	}


	public function testInsertChange()
	{
		if (in_array($this->section, ['array', 'mysql'], TRUE)) {
			Environment::skip("RETURNING clause not supported by '{$this->section}'");
		}

		$tag = new Tag('A');
		$this->orm->tags->persistAndFlush($tag);

		// dummy trigger sets computedProperty to ascii value of $name[0]
		Assert::same(ord($tag->name[0]), $tag->computedProperty);
	}

}


$test = new MapperTriggerChangeTest($dic);
$test->run();

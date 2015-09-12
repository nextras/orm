<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;

use Nextras\Orm\Collection\Helpers\FindByParserHelper;
use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class FindByParserHelperTest extends TestCase
{

	public function testParser()
	{
		$name = 'findByName';
		$args = ['jon'];
		Assert::true(FindByParserHelper::parse($name, $args));
		Assert::same('findBy', $name);
		Assert::same(['name' => 'jon'], $args);


		$name = 'getByFullNameAndEmail';
		$args = ['jon snow', 'castleblack@wall.7k'];
		Assert::true(FindByParserHelper::parse($name, $args));
		Assert::same('getBy', $name);
		Assert::same([
			'fullName' => 'jon snow',
			'email' => 'castleblack@wall.7k',
		], $args);
	}


	public function testWrongMehtodName()
	{
		$name = 'getName';
		$args = [];
		Assert::false(FindByParserHelper::parse($name, $args));


		$name = 'getBy';
		$args = [];
		Assert::false(FindByParserHelper::parse($name, $args));
	}


	public function testMissingArg()
	{
		Assert::throws(function () {
			$name = 'getByUrl';
			$args = [];
			FindByParserHelper::parse($name, $args);
		}, InvalidArgumentException::class, 'Missing argument for 1th parameter.');
	}

}


$test = new FindByParserHelperTest($dic);
$test->run();

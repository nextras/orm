<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Collection;

use Nextras\Orm\Entity\Collection\FindByParser;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class FindByParserTest extends TestCase
{

	public function testParser()
	{
		$name = 'findByName';
		$args = ['jon'];
		Assert::true(FindByParser::parse($name, $args));
		Assert::same('findBy', $name);
		Assert::same(['name' => 'jon'], $args);


		$name = 'getByFullNameAndEmail';
		$args = ['jon snow', 'castleblack@wall.7k'];
		Assert::true(FindByParser::parse($name, $args));
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
		Assert::false(FindByParser::parse($name, $args));


		$name = 'getBy';
		$args = [];
		Assert::false(FindByParser::parse($name, $args));
	}


	public function testMissingArg()
	{
		Assert::throws(function () {
			$name = 'getByUrl';
			$args = [];
			FindByParser::parse($name, $args);
		}, 'Nextras\Orm\InvalidArgumentException', 'Missing argument for 1th parameter.');
	}

}


$test = new FindByParserTest($dic);
$test->run();

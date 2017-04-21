<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Mockery;
use Nextras\Orm\Entity\Reflection\ModifierParser;
use Nextras\Orm\InvalidModifierDefinitionException;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class ConstantsExpansion
{
	const FOO1 = 1;
	const FOO2 = 2;
	const BAR = 'X';
	const BAR2 = 'Y';
}

class ModifierParserTest extends TestCase
{
	public function testMatchModifiers()
	{
		$parser = new ModifierParser();

		Assert::equal(
			['modifier a, 2'],
			$parser->matchModifiers('foo {modifier a, 2} bar')
		);

		Assert::equal(
			['modifier a, 2', 'modifier 2'],
			$parser->matchModifiers('foo {modifier a, 2} bar {modifier 2} baz')
		);

		Assert::equal(
			['modifier a, "}", \'{\', 2'],
			$parser->matchModifiers('foo {modifier a, "}", \'{\', 2} bar')
		);

		Assert::equal(
			['modifier a, "}", \'{\', 2', 'modifier a, "}", \'{\', 2'],
			$parser->matchModifiers('foo {modifier a, "}", \'{\', 2} bar {modifier a, "}", \'{\', 2} baz')
		);
	}


	public function testParsingModifier()
	{
		$reflection = Mockery::mock(\ReflectionClass::class);
		$parser = new ModifierParser();

		Assert::equal(
			['modifier', ['string', 'foo']],
			$parser->parse('modifier string, foo', $reflection)
		);

		Assert::equal(
			['modifier', ['baz' => ['foo', 'bar']]],
			$parser->parse('modifier baz=[foo, bar]', $reflection)
		);

		Assert::equal(
			['modifier', ['baz' => []]],
			$parser->parse('modifier baz=[]', $reflection)
		);

		Assert::equal(
			['modifier', ['baz' => ['foo, bar', 'foo', ',', ']']]],
			$parser->parse('modifier baz=["foo, bar", "foo", ",", "]"]', $reflection)
		);

		Assert::equal(
			['1:m', ['cascade' => ['persist', 'remove', 'refresh'], 'order' => ['property', 'DESC'], 'primary']],
			$parser->parse('1:m cascade=[persist, remove, refresh], order=[property, DESC], primary', $reflection)
		);

		Assert::equal(
			['1:m', ['Book::$author','order' => ['id', 'DESC']]],
			$parser->parse('1:m Book::$author, order=[id, DESC]', $reflection)
		);

		Assert::equal(
			['modifier', [true, false, null, 1, 2.3, "jon"]],
			$parser->parse('modifier true, false, NUll, 1, 2.3, jon', $reflection)
		);

		Assert::equal(
			['modifier', ['baz' => [['a', 'b'], ['c']]]],
			$parser->parse('modifier baz=[[a, b], [c]]', $reflection)
		);

		Assert::equal(
			['modifier', ['foo', ['bar']]],
			$parser->parse('modifier foo, [bar]', $reflection)
		);

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('foo=[', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {foo} has invalid token, expected string, keyword, or array.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier foo=[', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {modifier} has unclosed array argument.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier foo=[bar', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {modifier} has unclosed array argument.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier foo, bar] baz', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {modifier} misses argument separator.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier foo, bar[bar]', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {modifier} misses argument separator.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier foo, ]', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {modifier} mismatches brackets.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse(']', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier does not have a name.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier =[]', $reflection);
		}, InvalidModifierDefinitionException::class, 'Modifier {modifier} has invalid token, expected string, keyword, or array.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier, bar', $reflection);
		}, InvalidModifierDefinitionException::class, 'After the {modifier}\'s modifier name cannot be a comma separator.');
	}


	public function testContstatsExpansion()
	{
		$reflection = new \ReflectionClass(ConstantsExpansion::class);
		$parser = new ModifierParser();
		Assert::equal(
			['modifier', [1, 2, 'X', 'Y']],
			$parser->parse('modifier ConstantsExpansion::FOO*, \NextrasTests\Orm\Entity\Reflection\ConstantsExpansion::BAR*', $reflection)
		);

		Assert::equal(
			['modifier', [1, 2, 'X', 'Y']],
			$parser->parse('modifier self::FOO*, static::BAR*', $reflection)
		);

		Assert::equal(
			['modifier', [1, 2, 'X', 'Y']],
			$parser->parse('modifier self::*', $reflection)
		);

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier ConstantsExpansion::FOOD*', $reflection);
		}, InvalidModifierDefinitionException::class, 'No constant matches NextrasTests\Orm\Entity\Reflection\ConstantsExpansion::FOOD* pattern.');

		Assert::throws(function () use ($parser, $reflection) {
			$parser->parse('modifier ConstantsExpansion::UNKNOWN', $reflection);
		}, InvalidModifierDefinitionException::class, 'Constant NextrasTests\Orm\Entity\Reflection\ConstantsExpansion::UNKNOWN does not exist.');
	}
}

$test = new ModifierParserTest($dic);
$test->run();

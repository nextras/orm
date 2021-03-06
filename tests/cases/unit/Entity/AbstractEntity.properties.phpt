<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;


use Nextras\Orm\Exception\InvalidArgumentException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class AbstractEntityPropertiesTest extends TestCase
{
	public function testProperties(): void
	{
		Assert::throws(function (): void {
			$book = new Book();
			$book->getValue('blabla');
		}, InvalidArgumentException::class, 'Undefined property NextrasTests\Orm\Book::$blabla.');

		Assert::throws(function (): void {
			$book = new Book();
			$book->getValue('title2');
		}, InvalidArgumentException::class, 'Undefined property NextrasTests\Orm\Book::$title2, did you mean $title?');
	}
}


$test = new AbstractEntityPropertiesTest();
$test->run();

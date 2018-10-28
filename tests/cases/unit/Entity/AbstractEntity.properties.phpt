<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;

use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class AbstractEntityPropertiesTest extends TestCase
{
	public function testProperties()
	{
		Assert::exception(function () {
			$book = new Book();
			$book->getValue('blabla');
		}, InvalidArgumentException::class, 'Undefined property NextrasTests\Orm\Book::$blabla.');

		Assert::exception(function () {
			$book = new Book();
			$book->getValue('title2');
		}, InvalidArgumentException::class, 'Undefined property NextrasTests\Orm\Book::$title2, did you mean $title?');
	}
}


$test = new AbstractEntityPropertiesTest($dic);
$test->run();

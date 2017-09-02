<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;

use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\NullValueException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityNullValidationTest extends TestCase
{
	public function testSetNull()
	{
		Assert::throws(function () {
			$book = new Book();
			$book->title = null;
		}, InvalidArgumentException::class, 'Value for NextrasTests\Orm\Book::$title property is invalid.');

		Assert::throws(function () {
			$book = new Book();
			$book->author = null;
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$author is not nullable.');

		$book = new Book();
		$book->translator = null;
	}


	public function testGetNull()
	{
		Assert::throws(function () {
			$book = new Book();
			$book->title;
		}, InvalidStateException::class, 'Property NextrasTests\Orm\Book::$title is not set.');

		Assert::throws(function () {
			$book = new Book();
			$book->author;
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$author is not nullable.');
	}


	public function testHasValue()
	{
		$book = new Book();
		Assert::false($book->hasValue('title'));
		Assert::false($book->hasValue('translator'));
		Assert::false($book->hasValue('author'));
	}


	public function testValidationOnGetter()
	{
		$book = new Book();
		$book->hasValue('author');

		Assert::throws(function () use ($book) {
			$book->author;
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$author is not nullable.');
	}
}


$test = new EntityNullValidationTest($dic);
$test->run();

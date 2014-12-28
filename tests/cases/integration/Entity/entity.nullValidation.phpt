<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integrations;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityNullValidationTest extends TestCase
{

	public function testSetNull()
	{
		Assert::throws(function() {
			$book = new Book();
			$book->title = NULL;
		}, 'Nextras\Orm\InvalidArgumentException', 'Value for NextrasTests\Orm\Book::$title property is invalid.');

		Assert::throws(function() {
			$book = new Book();
			$book->author = NULL;
		}, 'Nextras\Orm\NullValueException', 'Property NextrasTests\Orm\Book::$author is not nullable.');

		$book = new Book();
		$book->translator = NULL;
	}


	public function testGetNull()
	{
		Assert::throws(function() {
			$book = new Book();
			$book->title;
		}, 'Nextras\Orm\InvalidStateException', 'Property NextrasTests\Orm\Book::$title is not set.');

		Assert::throws(function() {
			$book = new Book();
			$book->author;
		}, 'Nextras\Orm\NullValueException', 'Property NextrasTests\Orm\Book::$author is not nullable.');
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

		Assert::throws(function() use ($book) {
			$book->author;
		}, 'Nextras\Orm\NullValueException', 'Property NextrasTests\Orm\Book::$author is not nullable.');
	}

}


$test = new EntityNullValidationTest($dic);
$test->run();

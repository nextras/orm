<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityNullValidationTest extends TestCase
{

	public function testSetNull()
	{
		Assert::throws(function() {
			$book = new Book();
			$book->title = NULL;
		}, 'Nextras\Orm\InvalidArgumentException', 'Value for Nextras\Orm\Tests\Book::$title property is invalid.');

		Assert::throws(function() {
			$book = new Book();
			$book->author = NULL;
		}, 'Nextras\Orm\NullValueException', 'Property Nextras\Orm\Tests\Book::$author is not nullable.');

		$book = new Book();
		$book->translator = NULL;
	}


	public function testGetNull()
	{
		Assert::throws(function() {
			$book = new Book();
			$book->title;
		}, 'Nextras\Orm\InvalidArgumentException', 'Value for Nextras\Orm\Tests\Book::$title property is invalid.');

		Assert::throws(function() {
			$book = new Book();
			$book->author;
		}, 'Nextras\Orm\NullValueException', 'Property Nextras\Orm\Tests\Book::$author is not nullable.');
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
		}, 'Nextras\Orm\NullValueException', 'Property Nextras\Orm\Tests\Book::$author is not nullable.');
	}

}


$test = new EntityNullValidationTest($dic);
$test->run();

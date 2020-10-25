<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;


use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NullValueException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityNullValidationTest extends TestCase
{
	public function testSetNull(): void
	{
		Assert::throws(function (): void {
			$book = new Book();
			$book->title = null; // @phpstan-ignore-line
		}, InvalidArgumentException::class, 'Value for NextrasTests\Orm\Book::$title property is invalid.');

		Assert::throws(function (): void {
			$book = new Book();
			$book->author = null; // @phpstan-ignore-line
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$author is not nullable.');

		$book = new Book();
		$book->translator = null;
	}


	public function testGetNull(): void
	{
		Assert::throws(function (): void {
			$book = new Book();
			$book->getValue('title');
		}, InvalidStateException::class, 'Property NextrasTests\Orm\Book::$title is not set.');

		Assert::throws(function (): void {
			$book = new Book();
			$book->getValue('author');
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$author is not nullable.');
	}


	public function testHasValue(): void
	{
		$book = new Book();
		Assert::false($book->hasValue('title'));
		Assert::false($book->hasValue('translator'));
		Assert::false($book->hasValue('author'));
	}


	public function testValidationOnGetter(): void
	{
		$book = new Book();
		$book->hasValue('author');

		Assert::throws(function () use ($book): void {
			$book->getValue('author');
		}, NullValueException::class, 'Property NextrasTests\Orm\Book::$author is not nullable.');
	}
}


$test = new EntityNullValidationTest();
$test->run();

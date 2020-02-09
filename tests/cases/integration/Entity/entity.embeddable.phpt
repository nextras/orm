<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Entity;

use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\NullValueException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Currency;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Money;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityEmbeddableTest extends DataTestCase
{
	public function testBasic()
	{
		$book = $this->orm->books->getById(1);
		$book->price = new Money(1000, Currency::CZK());
		Assert::same(1000, $book->price->cents);
		Assert::same(Currency::CZK(), $book->price->currency);

		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getById(1);

		Assert::same(1000, $book->price->cents);
		Assert::same(Currency::CZK(), $book->price->currency);
	}


	public function testSetInvalid()
	{
		Assert::throws(function () {
			$book = new Book();
			$book->price = (object) ['price' => 100];
		}, InvalidArgumentException::class);

		Assert::throws(function () {
			$book = new Book();
			$book->price = (object) ['price' => 100, 'currency' => Currency::CZK()];
		}, InvalidArgumentException::class);
	}


	public function testNull()
	{
		$book = $this->orm->books->getById(1);

		$book->price = new Money(1000, Currency::CZK());
		Assert::same(1000, $book->price->cents);

		$book->price = null;
		Assert::null($book->price);
	}


	public function testNonNull()
	{
		$book = $this->orm->books->getById(1);
		$book->getMetadata()->getProperty('price')->isNullable = false;

		Assert::throws(function () use ($book) {
			$book->price = null;
		}, NullValueException::class);
	}
}


$test = new EntityEmbeddableTest($dic);
$test->run();

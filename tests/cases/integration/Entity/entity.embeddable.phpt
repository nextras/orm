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
	public function testBasic(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->price = new Money(1000, Currency::CZK());
		Assert::same(1000, $book->price->cents);
		Assert::same(Currency::CZK(), $book->price->currency);

		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getByIdChecked(1);

		Assert::notNull($book->price);
		Assert::same(1000, $book->price->cents);
		Assert::same(Currency::CZK(), $book->price->currency);

		$book->price = null;
		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getByIdChecked(1);
		Assert::null($book->price);
	}


	public function testMultiple(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->price = new Money(1000, Currency::CZK());
		$book->origPrice = new Money(330, Currency::EUR());

		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getByIdChecked(1);
		Assert::notNull($book->origPrice);
		Assert::same(330, $book->origPrice->cents);
	}


	public function testSetInvalid(): void
	{
		Assert::throws(function (): void {
			$book = new Book();
			// @phpstan-ignore-next-line
			$book->price = (object) ['price' => 100];
		}, InvalidArgumentException::class);

		Assert::throws(function (): void {
			$book = new Book();
			// @phpstan-ignore-next-line
			$book->price = (object) ['price' => 100, 'currency' => Currency::CZK()];
		}, InvalidArgumentException::class);
	}


	public function testNull(): void
	{
		$book = $this->orm->books->getByIdChecked(1);

		$book->price = new Money(1000, Currency::CZK());
		Assert::same(1000, $book->price->cents);

		$book->price = null;
		Assert::null($book->price);
	}


	public function testNonNull(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->getMetadata()->getProperty('price')->isNullable = false;

		Assert::throws(function () use ($book): void {
			$book->price = null;
		}, NullValueException::class);
	}
}


$test = new EntityEmbeddableTest($dic);
$test->run();

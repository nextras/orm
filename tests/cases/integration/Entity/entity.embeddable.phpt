<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Entity;


use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NullValueException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Money;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityEmbeddableTest extends DataTestCase
{
	public function testBasic(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->price = new Money(1000, 'CZK');
		Assert::same(1000, $book->price->cents);
		Assert::same('CZK', $book->price->currency->code);

		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getByIdChecked(1);

		Assert::notNull($book->price);
		Assert::same(1000, $book->price->cents);
		Assert::same('CZK', $book->price->currency->code);

		$book->price = null;
		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getByIdChecked(1);
		Assert::null($book->price);
	}


	public function testMultiple(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->price = new Money(1000, 'CZK');
		$book->origPrice = new Money(330, 'EUR');

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
			$book->price = (object) ['price' => 100, 'currency' => 'CZK'];
		}, InvalidArgumentException::class);
	}


	public function testNull(): void
	{
		$book = $this->orm->books->getByIdChecked(1);

		$book->price = new Money(1000, 'CZK');
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


	public function testInvalidRelationship(): void
	{
		Assert::throws(function (): void {
			$book = $this->orm->books->getByIdChecked(1);
			$book->price = new Money(1000, 'GBP');
		}, InvalidArgumentException::class, "Entity with primary key 'GBP' was not found.");
	}


	public function testRelationships(): void
	{
		Assert::throws(function (): void {
			$money = new Money(100, 'CZK');
			$money->currency;
		}, InvalidStateException::class, 'Relationship is not attached to a parent entity.');

		$money = new Money(100, 'CZK');
		$book = $this->orm->books->getByIdChecked(1);
		$book->price = $money;
		Assert::same('CZK', $money->currency->code);

		Assert::throws(function (): void {
			$money = new Money(100, 'CZK');
			$book = new Book();
			$book->price = $money;
			$money->currency;
		}, InvalidStateException::class, 'Entity is not attached to a repository. Use IEntity::isAttached() method to check the state.');

		$money = new Money(100, $this->orm->currencies->getById('CZK'));
		Assert::same('CZK', $money->currency->code);
	}
}


$test = new EntityEmbeddableTest($dic);
$test->run();

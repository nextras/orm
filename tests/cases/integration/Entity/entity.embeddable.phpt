<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Entity;

use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Money;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityEmbeddableTest extends DataTestCase
{
	public function testBasic()
	{
		$book = $this->orm->books->getById(1);
		Assert::null($book->price);

		$book->price = new Money(1000, Money::CZK);
		Assert::same(1000, $book->price->cents);
		Assert::same(Money::CZK, $book->price->currency);

		$this->orm->persistAndFlush($book);
		$this->orm->clear();

		$book = $this->orm->books->getById(1);
		Assert::same(1000, $book->price->cents);
		Assert::same(Money::CZK, $book->price->currency);
	}


	public function testSetInvalid()
	{
		Assert::throws(function () {
			$book = new Book();
			$book->price = (object) ['price' => 100];
		}, InvalidArgumentException::class);
	}
}


$test = new EntityEmbeddableTest($dic);
$test->run();

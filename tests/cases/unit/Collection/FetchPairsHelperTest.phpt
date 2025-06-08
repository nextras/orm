<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use ArrayIterator;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Currency;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\Money;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class FetchPairsHelperTest extends TestCase
{
	public function testParser(): void
	{
		$data = new ArrayIterator([
			$one = $this->e(
				Author::class,
				[
					'id' => 10,
					'name' => 'jon snow',
					'web' => 'http://castleblack.wall.7k',
					'bornOn' => new DateTimeImmutable('2014-01-01'),
				]
			),
			$two = $this->e(
				Author::class,
				[
					'id' => 12,
					'name' => 'oberyn martell',
					'web' => 'https://ob.martell.7k',
					'bornOn' => new DateTimeImmutable('2014-01-03'),
				]
			),
		]);

		Assert::same(
			['http://castleblack.wall.7k', 'https://ob.martell.7k'],
			FetchPairsHelper::process($data, null, 'web')
		);

		Assert::same(
			[10 => $one, 12 => $two],
			FetchPairsHelper::process($data, 'id', null)
		);

		Assert::same(
			[
				10 => 'http://castleblack.wall.7k',
				12 => 'https://ob.martell.7k',
			],
			FetchPairsHelper::process($data, 'id', 'web')
		);

		Assert::same(
			[
				'2014-01-01T00:00:00+01:00' => $one,
				'2014-01-03T00:00:00+01:00' => $two,
			],
			FetchPairsHelper::process($data, 'bornOn', null)
		);

		Assert::same(
			[
				'2014-01-01T00:00:00+01:00' => 'http://castleblack.wall.7k',
				'2014-01-03T00:00:00+01:00' => 'https://ob.martell.7k',
			],
			FetchPairsHelper::process($data, 'bornOn', 'web')
		);
	}


	public function testNested(): void
	{
		$data = new ArrayIterator([
			$one = $this->e(
				Ean::class,
				[
					'code' => '123',
					'book' => $book1 = $this->e(
						Book::class,
						[
							'id' => 10,
							'title' => 'Wall',
							'author' => $this->e(
								Author::class,
								[
									'name' => 'jon snow',
								]
							),
						]
					),
				]
			),
			$two = $this->e(
				Ean::class,
				[
					'code' => '456',
					'book' => $book2 = $this->e(
						Book::class,
						[
							'id' => 12,
							'title' => 'Landing',
							'author' => $this->e(
								Author::class,
								[
									'name' => 'Little Finger',
								]
							),
						]
					),
				]
			),
		]);

		Assert::same(
			[
				'123' => 'Wall',
				'456' => 'Landing',
			],
			FetchPairsHelper::process($data, 'code', 'book->title')
		);

		Assert::same(
			[
				'123' => $book1,
				'456' => $book2,
			],
			FetchPairsHelper::process($data, 'code', 'book')
		);

		Assert::same(
			[
				'Wall' => 'jon snow',
				'Landing' => 'Little Finger',
			],
			FetchPairsHelper::process($data, 'book->title', 'book->author->name')
		);

		Assert::same(
			[
				'jon snow' => '123',
				'Little Finger' => '456',
			],
			FetchPairsHelper::process($data, 'book->author->name', 'code')
		);

		Assert::same(
			[
				10 => $one,
				12 => $two,
			],
			FetchPairsHelper::process($data, 'book->id', null)
		);
	}


	public function testEmbeddable(): void
	{
		$data = new ArrayIterator([
			$this->e(
				Book::class,
				['price' => new Money(100, Currency::CZK)]
			),
			$this->e(
				Book::class,
				['price' => new Money(200, Currency::CZK)]
			),
		]);
		Assert::same(
			[100, 200],
			FetchPairsHelper::process($data, null, 'price->cents')
		);
	}


	public function testUnsupportedHasMany(): void
	{
		Assert::throws(function (): void {
			$data = new ArrayIterator([
				$one = $this->e(
					Author::class,
					[
						'id' => 10,
						'books' => [
							$this->e(Book::class),
							$this->e(Book::class),
						],
					]
				),
			]);
			FetchPairsHelper::process($data, null, 'books->id');
		}, InvalidStateException::class, "Part 'books' of the chain expression does not select an IEntity nor an IEmbeddable.");
	}


	public function testMissingArguments(): void
	{
		Assert::throws(function (): void {
			FetchPairsHelper::process(new ArrayIterator([]), null, null);
		}, InvalidArgumentException::class, 'FetchPairsHelper requires defined key or value.');
	}
}


$test = new FetchPairsHelperTest();
$test->run();

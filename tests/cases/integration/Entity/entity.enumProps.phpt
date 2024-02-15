<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use DateTimeImmutable;
use inc\model\book\GenreEnum;
use Nextras\Orm\Exception\InvalidArgumentException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Currency;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Money;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityEnumPropTest extends DataTestCase
{

	public function testEnumOnEntity(): void
	{
		/** @var Book $book */
		$book = $this->orm->books->findAll()->fetch();

		Assert::notNull($book);

		Assert::same(GenreEnum::SCIFI, $book->genre);
	}


	public function testAddEntityWithEnum(): void
	{
		$bookName = 'Book 5';

		$book = $this->createBookEntity($bookName);
		$book->genre = GenreEnum::ROMANCE;
		$this->orm->books->persistAndFlush($book);

		Assert::same(GenreEnum::ROMANCE, $book->genre);

		$entity = $this->orm->books->getBy(['title' => $bookName]);
		Assert::notNull($entity);

		Assert::type(GenreEnum::class, $entity->genre);

		Assert::same(GenreEnum::ROMANCE, $entity->genre);
	}


	private function createBookEntity(string $title): Book
	{
		$book = new Book();
		$book->title = $title;
		$book->publishedAt = new DateTimeImmutable('2021-12-14 21:10:02');
		$book->price = new Money(150, Currency::CZK);
		$this->createAuthorToBook($book);
		$this->createPublisherToBook($book);
		$this->orm->books->persist($book);

		return $book;
	}


	private function createAuthorToBook(Book $book): void
	{
		$author1 = new Author();
		$author1->name = 'Writer 1';
		$author1->web = 'http://example.com/1';
		$this->orm->authors->persist($author1);

		$book->author = $author1;
	}


	private function createPublisherToBook(Book $book): void
	{
		$publisher1 = new Publisher();
		$publisher1->name = 'Nextras publisher A';
		$this->orm->publishers->persist($publisher1);

		$book->publisher = $publisher1;
	}


	public function testAddEntityWithDefaultEnum(): void
	{
		$bookName = 'Book 6';

		$book = $this->createBookEntity($bookName);
		$this->orm->books->persistAndFlush($book);

		Assert::same(GenreEnum::FANTASY, $book->genre);

		$entity = $this->orm->books->getBy(['title' => $bookName]);
		Assert::notNull($entity);

		Assert::type(GenreEnum::class, $entity->genre);

		Assert::same(GenreEnum::FANTASY, $entity->genre);
	}


	public function testAddEntityWithUnknownEnum(): void
	{
		$bookName = 'Book 7';

		$book = $this->createBookEntity($bookName);
		// @phpstan-ignore-next-line
		$book->genre = 'documentary';

		Assert::exception(function () use ($book) {
			$this->orm->books->persistAndFlush($book);
		}, InvalidArgumentException::class);

	}

}


$test = new EntityEnumPropTest();
$test->run();

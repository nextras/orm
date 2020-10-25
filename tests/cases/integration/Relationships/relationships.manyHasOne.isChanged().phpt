<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Orm\Relationships\HasOne;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsManyHasOneIsChangedTest extends TestCase
{
	public function testBasic(): void
	{
		$author1 = $this->e(Author::class);
		$author2 = $this->e(Author::class);

		$book = $this->e(Book::class);

		Assert::null($book->translator);
		$property = $book->getProperty('translator');
		Assert::type(HasOne::class, $property);
		Assert::false($property->isModified());

		$book->translator = $author1;
		Assert::same(1, $author1->translatedBooks->count());
		Assert::same(0, $author2->translatedBooks->count());

		$book->translator = $author2;
		Assert::same(0, $author1->translatedBooks->count());
		Assert::same(1, $author2->translatedBooks->count());

		$book->translator = null;
		Assert::same(0, $author1->translatedBooks->count());
		Assert::same(0, $author2->translatedBooks->count());

		$property = $book->getProperty('author');
		Assert::type(HasOne::class, $property);
		Assert::true($property->isModified());

		$book->translator = null;
		$property = $book->getProperty('author');
		Assert::type(HasOne::class, $property);
		Assert::true($property->isModified());

		$property = $book->getProperty('translator');
		Assert::type(HasOne::class, $property);
		Assert::true($property->isModified());
	}
}


$test = new RelationshipsManyHasOneIsChangedTest();
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasOneTest extends DataTestCase
{

	public function testBasics(): void
	{
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');
		$authors = [];

		foreach ($books as $book) {
			$authors[] = $book->author->id;
		}

		Assert::same([1, 1, 2, 2], $authors);
	}


	public function testHasValue(): void
	{
		$bookA = $this->orm->books->getByIdChecked(1);
		Assert::true(isset($bookA->author));

		$bookB = new Book();
		Assert::false(isset($bookB->author));

		$bookB->author = new Author();
		Assert::true(isset($bookB->author));
	}


	public function testTranslator(): void
	{
		// id > 1 => to start collection with entity.translator = NULL
		$books = $this->orm->books->findBy(['id>' => 1])->orderBy('id');
		$translators = [];

		foreach ($books as $book) {
			$translators[] = $book->translator !== null ? $book->translator->id : null;
		}

		Assert::same([null, 2, 2], $translators);
	}


	public function testEmptyEntityPreloadContainer(): void
	{
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');
		$authors = [];

		foreach ($books as $book) {
			$book->setPreloadContainer(null);
			$authors[] = $book->author->id;
		}

		Assert::same([1, 1, 2, 2], $authors);
	}


	public function testPersistenceHasOne(): void
	{
		$author = new Author();
		$author->name = 'Jon Snow';

		$book = new Book();
		$book->title = 'A new book';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->books->persistAndFlush($book);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);
	}


	public function testAutoConnection(): void
	{
		$author1 = $this->orm->authors->getByIdChecked(1);

		$book = new Book();
		$book->title = 'A new book';
		$book->publisher = $this->orm->publishers->getByIdChecked(1);
		$author1->translatedBooks->add($book);
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);

		$book = new Book();
		$book->title = 'The second new book';
		$book->publisher = $this->orm->publishers->getByIdChecked(1);
		$book->translator = $author1;
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);

		$author2 = $this->orm->authors->getByIdChecked(2);
		$author2->translatedBooks->add($book);
		Assert::false($author1->translatedBooks->has($book));
		Assert::true($author2->translatedBooks->has($book));
		Assert::same($book->translator, $author2);

		$book->translator = $author1;
		Assert::false($author2->translatedBooks->has($book));
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);
	}


	public function testCache(): void
	{
		$author = $this->orm->authors->getByIdChecked(2);

		$books = $author->books->toCollection()->limitBy(1);
		$publishers = [];
		foreach ($books as $book) {
			$publishers[] = $book->publisher->name;
		}
		Assert::equal(['Nextras publisher A'], $publishers);

		$publishers = [];
		$books = $author->books;
		foreach ($books as $book) {
			$publishers[] = $book->publisher->name;
		}
		Assert::equal(['Nextras publisher A', 'Nextras publisher C'], $publishers);
	}


	public function testProperAggregation(): void
	{
		$books = $this->orm->books->findBy([
			'tags->id' => 1,
			'publisher->name' => 'Nextras publisher A',
		]);
		Assert::same($books->count(), 1);
	}
}


$test = new RelationshipManyHasOneTest();
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;
use Tester\Environment;
use function count;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasManyTest extends DataTestCase
{
	public function testBasics(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);

		$collection = $author->books->toCollection()->findBy(['title!=' => 'Book 1']);
		Assert::equal(1, $collection->count());
		Assert::equal(1, $collection->countStored());
		$fetched = $collection->fetch();
		Assert::notNull($fetched);
		Assert::equal('Book 2', $fetched->title);

		$collection = $author->books->toCollection()->findBy(['title!=' => 'Book 3']);
		Assert::equal(2, $collection->count());
		Assert::equal(2, $collection->countStored());
		$fetched = $collection->fetch();
		Assert::notNull($fetched);
		Assert::equal('Book 2', $fetched->title);
		$fetched = $collection->fetch();
		Assert::notNull($fetched);
		Assert::equal('Book 1', $fetched->title);

		$collection = $author->books->toCollection()->resetOrderBy()->findBy(['title!=' => 'Book 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal(2, $collection->countStored());
		$fetched = $collection->fetch();
		Assert::notNull($fetched);
		Assert::equal('Book 1', $fetched->title);
		$fetched = $collection->fetch();
		Assert::notNull($fetched);
		Assert::equal('Book 2', $fetched->title);
	}


	public function testCountOnCompositePkInTargetTable(): void
	{
		// add another tag to have >1 tags followers for tag#2
		$tag = $this->orm->tags->getByIdChecked(1);
		$author = $this->orm->authors->getByIdChecked(2);
		$tagFollower = new TagFollower();
		$tagFollower->author = $author;
		$tagFollower->tag = $tag;
		$this->orm->persistAndFlush($tagFollower);
		$this->orm->clear();

		$tag = $this->orm->tags->getByIdChecked(1);
		Assert::same(2, $tag->tagFollowers->countStored());
	}


	public function testWithDifferentPrimaryKey(): void
	{
		$publisher = $this->orm->publishers->getByIdChecked(1);
		$titles = [];
		foreach ($publisher->books as $book) {
			$titles[] = $book->title;
		}

		Assert::equal(['Book 1', 'Book 4'], $titles);
	}


	public function testRawValue(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		Assert::same([2, 1], $author->books->getRawValue());

		$this->orm->books->remove($this->orm->books->getByIdChecked(1));
		Assert::same([2], $author->books->getRawValue());

		$book = new Book();
		$book->author = $author;
		$book->title = 'Test book';
		$book->publisher = 1;

		$this->orm->books->attach($book);

		Assert::same([2], $author->books->getRawValue());

		$this->orm->books->persistAndFlush($book);

		Assert::same([5, 2], $author->books->getRawValue());
	}


	public function testPersistence(): void
	{
		$publisher = $this->e(Publisher::class, ['name' => 'Publisher']);
		$author1 = $this->e(Author::class, ['name' => 'A1']);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 1', 'publisher' => $publisher]);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 2', 'publisher' => $publisher]);

		$author2 = $this->e(Author::class, ['name' => 'A2']);
		$this->e(Book::class, ['author' => $author2, 'title' => 'Book 3', 'publisher' => $publisher]);
		$this->e(Book::class, ['author' => $author2, 'title' => 'Book 4', 'publisher' => $publisher]);

		$author3 = $this->e(Author::class, ['name' => 'A3']);
		$this->e(Book::class, ['author' => $author3, 'title' => 'Book 5', 'publisher' => $publisher]);
		$this->e(Book::class, ['author' => $author3, 'title' => 'Book 6', 'publisher' => $publisher]);

		$this->orm->authors->persist($author1);
		$this->orm->authors->persist($author2);
		$this->orm->authors->persist($author3);
		$this->orm->flush();

		$books = [];
		foreach ($author1->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['Book 2', 'Book 1'], $books);

		$books = [];
		foreach ($author2->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['Book 4', 'Book 3'], $books);

		$books = [];
		foreach ($author3->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['Book 6', 'Book 5'], $books);
	}


	public function testDefaultOrderingOnEmptyCollection(): void
	{
		$author1 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 1', 'id' => 9]);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 2', 'id' => 8]);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book 2', 'id' => 10]);

		$ids = [];
		foreach ($author1->books as $book) {
			$ids[] = $book->id;
		}
		Assert::same([10, 9, 8], $ids);
	}


	public function testOrderingWithJoins(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		Assert::notNull($book->translator);
		$books = $book->translator->books->toCollection()->orderBy('ean->code')->fetchAll();
		Assert::count(2, $books);
	}


	public function testLimit(): void
	{
		$book = new Book();
		$book->title = 'Book 5';
		$book->author = 1;
		$book->publisher = 1;
		$this->orm->books->persistAndFlush($book);

		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');

		$books = [];
		$counts = [];
		$countsStored = [];
		foreach ($authors as $author) {
			$booksLimited = $author->books->toCollection()->limitBy(2)->resetOrderBy()
				->orderBy('title', ICollection::DESC);
			foreach ($booksLimited as $bookLimited) {
				$books[] = $bookLimited->id;
			}
			$counts[] = $booksLimited->count();
			$countsStored[] = $booksLimited->countStored();
		}

		Assert::same([5, 2, 4, 3], $books);
		Assert::same([2, 2], $counts);
		Assert::same([2, 2], $countsStored);
	}


	public function testEmptyEntityPreloadContainer(): void
	{
		$books = [];

		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');
		foreach ($authors as $author) {
			$author->setPreloadContainer(null);
			foreach ($author->books as $book) {
				$books[] = $book->id;
			}
		}

		Assert::same([2, 1, 4, 3], $books);
	}


	public function testCachingBasic(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		$books = $author->books->toCollection()->findBy(['translator' => null]);
		Assert::same(1, $books->count());

		$book = $books->fetch();
		Assert::notNull($book);
		$book->translator = $author;
		$this->orm->books->persistAndFlush($book);

		$books = $author->books->toCollection()->findBy(['translator' => null]);
		Assert::same(0, $books->count());
	}


	public function testUniqueConstraintException(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Only for DbalMapper');
		}

		$tag = $this->orm->tags->getByIdChecked(2);
		foreach ([2, 1] as $tagFollowerId) {
			try {
				$tagFollower = new TagFollower();
				$tagFollower->tag = $tag;
				$tagFollower->author = $tagFollowerId;
				$this->orm->tagFollowers->persistAndFlush($tagFollower, false);
				Assert::true($tagFollower->isPersisted());
			} catch (UniqueConstraintViolationException $e) {
				$mapper = $this->orm->tagFollowers->getMapper();
				Assert::type(DbalMapper::class, $mapper);
				$mapper->rollback();
				\assert(isset($tagFollower));
				Assert::false($tagFollower->isPersisted());
			}
		}

		$connection = $this->container->getByType(IConnection::class);
		$pairs = $connection->query('SELECT author_id FROM tag_followers WHERE tag_id = 2 ORDER BY author_id')
			->fetchPairs(null, 'author_id');
		Assert::same([1, 2], $pairs);

		$this->orm->refreshAll(true);

		$ids = [];
		foreach ($tag->tagFollowers->orderBy('author') as $tagFollower) {
			$ids[] = $tagFollower->author->id;
			Assert::true($tagFollower->isPersisted());
		}
		sort($ids);
		Assert::same([1, 2], $ids);
	}


	public function testCountAfterRemoveAndFlushAndCount(): void
	{
		$author = new Author();
		$author->name = 'The Imp';
		$author->web = 'localhost';
		$author->born = new DateTimeImmutable('2000-01-01 12:12:12');

		$publisher = new Publisher();
		$publisher->name = 'Valyria';

		$book = new Book();
		$book->author = $author;
		$book->title = 'The Wall';
		$book->publisher = $publisher;
		$book->translator = $author;

		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, count($author->books));

		foreach ($author->books as $innerBook) {
			$this->orm->books->remove($innerBook);
		}

		Assert::same(0, count($author->books));

		$this->orm->books->flush();

		Assert::same(0, count($author->books));

		$book3 = new Book();
		$book3->author = $author;
		$book3->title = 'The Wall III';
		$book3->publisher = $publisher;

		Assert::same(1, count($author->books));

		$this->orm->books->persist($book3);

		Assert::same(1, count($author->books));
	}


	public function testCountStoredOnOneHasManyRelationshipCondition(): void
	{
		$publisher = $this->orm->publishers->getByIdChecked(1);
		$books = $publisher->books->toCollection()->findBy([
			'tags->id' => 1,
		]);
		Assert::same(1, $books->countStored());

		$books = $publisher->books->toCollection()->findBy([
			ICollection::OR,
			'title' => 'Book 1',
			'tags->id' => 1,
		]);
		Assert::same(1, $books->countStored());
	}


	public function testSameTableJoinWithImplicitAggregation(): void
	{
		$books = $this->orm->books->findBy([
			ICollection::OR,
			['tags->id' => [1]],
			['tags->id' => null], // no match
		]);

		Assert::same(1, $books->countStored());
		Assert::same(1, $books->count());
	}
}


$test = new RelationshipOneHasManyTest();
$test->run();

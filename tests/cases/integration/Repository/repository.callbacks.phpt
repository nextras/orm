<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Orm\Entity\IEntity;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryCallbacksTest extends DataTestCase
{

	public function testOnBeforePersist(): void
	{
		$author = new Author();
		$author->name = 'Test';

		$this->orm->authors->onBeforePersist[] = function (IEntity $author): void {
			\assert($author instanceof Author);
			$book = new Book();
			$book->title = 'Test Book';

			$author->books->add($book);

			$book->publisher = new Publisher();
			$book->publisher->name = 'Pub';
		};

		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, $author->books->count());
		foreach ($author->books as $book) {
			Assert::true($book->isPersisted());
		}
	}


	public function testOnFlush(): void
	{
		$allFlush = [];
		$this->orm->onFlush[] = function (array $persisted, array $removed) use (&$allFlush): void {
			foreach ($persisted as $persistedE) {
				$allFlush[] = $persistedE;
			}
			foreach ($removed as $removedE) {
				$allFlush[] = $removedE;
			}
		};

		$booksFlush = [];
		$this->orm->books->onFlush[] = function (array $persisted, array $removed) use (&$booksFlush): void {
			foreach ($persisted as $persistedE) {
				$booksFlush[] = $persistedE;
			}
			foreach ($removed as $removedE) {
				$booksFlush[] = $removedE;
			}
		};

		$author = new Author();
		$author->name = 'Test';

		$this->orm->authors->persistAndFlush($author);
		Assert::same([$author], $allFlush);
		Assert::same([], $booksFlush);

		$publisher = new Publisher();
		$publisher->name = 'Pub';

		$book = new Book();
		$book->title = 'Book';
		$book->author = $author;
		$book->publisher = $publisher;

		$this->orm->books->persistAndFlush($book);

		Assert::same([$author, $book, $publisher], $allFlush);
		Assert::same([$book], $booksFlush);

		$this->orm->books->persistAndFlush($book);

		Assert::same([$author, $book, $publisher], $allFlush);
		Assert::same([$book], $booksFlush);
	}

}


$test = new RepositoryCallbacksTest();
$test->run();

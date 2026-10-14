<?php declare(strict_types = 1);

/**
 * Guarantees provided by the identity map.
 *
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryIdentityMapSemanticsTest extends DataTestCase
{
	public function testGetByIdReturnsTheSameInstanceWithoutQuery(): void
	{
		$book = $this->orm->books->getByIdChecked(1);

		$queries = $this->getQueries(function () use ($book): void {
			Assert::same($book, $this->orm->books->getByIdChecked(1));
			Assert::same($book, $this->orm->books->getByIdChecked(1));
		});
		if ($queries !== null) {
			Assert::count(0, $queries);
		}
	}


	public function testCollectionsAndGetByIdShareInstances(): void
	{
		$books = $this->orm->books->findAll()->orderBy('id')->fetchAll();
		$book = $this->orm->books->getByIdChecked(1);
		Assert::same($books[0], $book);

		$book->title = 'Changed';
		Assert::same('Changed', $books[0]->title);
		Assert::same('Changed', $this->orm->books->findBy(['id' => 1])->fetchChecked()->title);
	}


	public function testUnflushedChangeIsNotOverwrittenByReload(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$book->title = 'Changed';

		// another code path loads the same row again; it must see the modified instance, not a fresh DB copy
		$reloaded = $this->orm->books->findBy(['author' => 1])->orderBy('id')->fetch();
		Assert::same($book, $reloaded);
		Assert::true($book->isModified());
	}


	public function testRelationshipsResolveToTheSameInstances(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$author = $book->author;

		Assert::same($author, $this->orm->authors->getByIdChecked(1));

		$authorsBooks = iterator_to_array($author->books);
		Assert::contains($book, $authorsBooks);
		foreach ($authorsBooks as $authorsBook) {
			Assert::same($author, $authorsBook->author);
		}
	}


	public function testNewlyAddedEntityIsVisibleThroughRelationship(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		$book = new Book();
		$book->title = 'New';
		$book->author = $author;
		$book->publisher = 1;
		$this->orm->books->persistAndFlush($book);

		Assert::same($book, $this->orm->books->getByIdChecked($book->id));
		Assert::contains($book, iterator_to_array($author->books));
	}


	public function testClearDetachesAllLoadedEntities(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$author = $book->author;
		$this->orm->clear();

		Assert::false($book->isAttached());
		Assert::false($author->isAttached());
		Assert::notSame($book, $this->orm->books->getByIdChecked(1));
	}


	public function testRefreshAllUpdatesLoadedInstances(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test is only for Dbal mapper.');
		}

		$book = $this->orm->books->getByIdChecked(1);
		$book->title = 'Unflushed';
		$this->orm->refreshAll(true);

		Assert::same('Book 1', $book->title);
		Assert::false($book->isModified());
	}
}


$test = new RepositoryIdentityMapSemanticsTest();
$test->run();

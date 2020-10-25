<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Utils\CallbackQueryLogger;
use Nextras\Orm\Exception\LogicException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\Tag;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityRelationshipsTest extends DataTestCase
{
	public function testBasics(): void
	{
		$author = new Author();
		$author->name = 'Jon Snow';

		$publisher = new Publisher();
		$publisher->name = '7K';

		$book = new Book();
		$book->title = 'A new book';
		$book->author = $author;
		$book->publisher = $publisher;
		$book->tags->set([new Tag('Awesome')]);

		$this->orm->books->persistAndFlush($book);

		Assert::true($author->isAttached());
		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);

		Assert::true($book->isAttached());
		Assert::true($book->isPersisted());
		Assert::false($book->isModified());
		Assert::same(5, $book->id);

		Assert::same(1, $book->tags->count());
		Assert::same(1, $book->tags->countStored());
		Assert::same('Awesome', $book->tags->toCollection()->fetch()->name);
	}


	public function testDeepTraversalHasOne(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip();
		}

		$queries = [];
		$connection = $this->container->getByType(Connection::class);
		$connection->addLogger(new CallbackQueryLogger(function ($query) use (& $queries): void {
			$queries[$query] = $queries[$query] ?? 1;
		}));

		$authors = [];
		foreach ($this->orm->tags->findAll() as $tag) {
			foreach ($tag->books as $book) {
				$authors[] = $book->author->id;
			}
		}

		Assert::same([1, 1, 1, 1, 2], $authors);
		Assert::equal([], array_filter($queries, function ($count): bool {
			return $count != 1;
		}));
	}


	public function testDeepTraversalManyHasMany(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip();
		}

		$queries = [];
		$connection = $this->container->getByType(IConnection::class);
		$connection->addLogger(new CallbackQueryLogger(function ($query) use (& $queries): void {
			$queries[$query] = isset($queries[$query]) ? $queries[$query] : 1;
		}));

		$tags = [];
		foreach ($this->orm->authors->findAll() as $author) {
			foreach ($author->books as $book) {
				foreach ($book->tags as $tag) {
					$tags[] = $tag->id;
				}
			}
		}

		Assert::same([2, 3, 1, 2, 3], $tags);
		Assert::equal([], array_filter($queries, function ($count): bool {
			return $count != 1;
		}));
	}


	public function testSetRelationships(): void
	{
		Assert::throws(function (): void {
			$author = new Author();
			// @phpstan-ignore-next-line
			$author->books = [];
		}, LogicException::class, 'You cannot set relationship collection value in NextrasTests\Orm\Author::$books directly.');
	}
}


$test = new EntityRelationshipsTest();
$test->run();

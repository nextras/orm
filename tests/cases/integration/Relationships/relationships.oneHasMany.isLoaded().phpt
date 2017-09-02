<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;

use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyIsLoadedTest extends TestCase
{
	public function testIsLoaded()
	{
		$author1 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author1]);
		$author2 = $this->e(Author::class);
		$this->e(Book::class, ['author' => $author2]);
		$this->orm->authors->persist($author1);
		$this->orm->authors->persist($author2);
		$this->orm->authors->flush();

		foreach ($this->orm->authors->findAll() as $author) {
			Assert::true($author->books->isLoaded());
		}

		foreach ($this->orm->authors->findAll() as $author) {
			/** @noinspection PhpStatementHasEmptyBodyInspection */
			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach ($author->books as $book) {
			}
			Assert::true($author->books->isLoaded());
		}
	}
}


$test = new RelationshipsOneHasManyIsLoadedTest($dic);
$test->run();

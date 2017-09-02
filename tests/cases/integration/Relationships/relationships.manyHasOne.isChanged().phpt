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


class RelationshipsManyHasOneIsChangedTest extends TestCase
{
	public function testBasic()
	{
		/** @var Author $author1 */
		/** @var Author $author2 */
		$author1 = $this->e(Author::class);
		$author2 = $this->e(Author::class);

		/** @var Book $book */
		$book = $this->e(Book::class);

		Assert::null($book->translator);

		$book->translator = $author1;
		Assert::same(1, $author1->translatedBooks->count());
		Assert::same(0, $author2->translatedBooks->count());

		$book->translator = $author2;
		Assert::same(0, $author1->translatedBooks->count());
		Assert::same(1, $author2->translatedBooks->count());

		$book->translator = null;
		Assert::same(0, $author1->translatedBooks->count());
		Assert::same(0, $author2->translatedBooks->count());

		Assert::true($book->getProperty('author')->isModified());

		$book->translator = null;
		Assert::true($book->getProperty('author')->isModified());
	}
}


$test = new RelationshipsManyHasOneIsChangedTest($dic);
$test->run();

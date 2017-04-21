<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Repository;

use Mockery;
use Nextras\Orm\Repository\PersistenceHelper;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class PersistenceHelperTest extends TestCase
{

	public function testNotCycle()
	{
		$publisher = new Publisher();
		$publisher->name = 'Jupiter Mining Corporation';

		$author = new Author();
		$author->name = 'Arnold Judas Rimmer';

		$translator = new Author();
		$translator->name = 'Dave Lister';
		$translator->favoredBy->add($author);

		$book = new Book();
		$book->title = 'Better Than Life';
		$book->publisher = $publisher;
		$book->author = $author;
		$book->translator = $translator;

		Assert::same(
			[
				$translator, $author,
				$translator->favoredBy,
				$translator->books,
				$publisher, $book, $translator->translatedBooks,
				$translator->tagFollowers,
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->tags,
				$publisher->books,
			],
			array_values(PersistenceHelper::getCascadeQueue($author, $this->orm, true))
		);

		Assert::same(
			[
				$translator,
				$author, $translator->favoredBy,
				$translator->books,
				$publisher, $book, $translator->translatedBooks,
				$translator->tagFollowers,
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->tags,
				$publisher->books,
			],
			array_values(PersistenceHelper::getCascadeQueue($translator, $this->orm, true))
		);

		Assert::same(
			[
				$translator, $author, $publisher, $book,
				$translator->favoredBy,
				$translator->books,
				$translator->translatedBooks,
				$translator->tagFollowers,
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->tags,
				$publisher->books,
			],
			array_values(PersistenceHelper::getCascadeQueue($book, $this->orm, true))
		);


		Assert::same(
			[
				$publisher,
				$translator, $author, $book, $publisher->books,
				$translator->favoredBy,
				$translator->books,
				$translator->translatedBooks,
				$translator->tagFollowers,
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->tags,
			],
			array_values(PersistenceHelper::getCascadeQueue($publisher, $this->orm, true))
		);
	}

}


$test = new PersistenceHelperTest($dic);
$test->run();

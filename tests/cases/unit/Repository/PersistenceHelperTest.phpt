<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Repository;


use Nextras\Orm\Repository\PersistenceHelper;
use Nextras\Orm\Repository\PersistenceMode;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class PersistenceHelperTest extends TestCase
{

	public function testNotCycle(): void
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
				$translator,
				$author,
				$translator->getProperty('favoriteAuthor'),
				$translator->favoredBy,
				$translator->books,
				$publisher,
				$book,
				$translator->translatedBooks,
				$translator->tagFollowers,
				$author->getProperty('favoriteAuthor'),
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->getProperty('author'),
				$book->getProperty('translator'),
				$book->tags,
				$publisher->books,
				$book->getProperty('publisher'),
			],
			array_values(PersistenceHelper::getCascadeQueue($author, PersistenceMode::Persist, $this->orm, true)[0])
		);

		Assert::same(
			[
				$translator,
				$translator->getProperty('favoriteAuthor'),
				$author,
				$translator->favoredBy,
				$translator->books,
				$publisher,
				$book,
				$translator->translatedBooks,
				$translator->tagFollowers,
				$author->getProperty('favoriteAuthor'),
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->getProperty('author'),
				$book->getProperty('translator'),
				$book->tags,
				$publisher->books,
				$book->getProperty('publisher'),
			],
			array_values(PersistenceHelper::getCascadeQueue($translator, PersistenceMode::Persist, $this->orm, true)[0])
		);

		Assert::same(
			[
				$translator,
				$author,
				$publisher,
				$book,
				$translator->getProperty('favoriteAuthor'),
				$translator->favoredBy,
				$translator->books,
				$translator->translatedBooks,
				$translator->tagFollowers,
				$author->getProperty('favoriteAuthor'),
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->getProperty('author'),
				$book->getProperty('translator'),
				$book->tags,
				$publisher->books,
				$book->getProperty('publisher'),
			],
			array_values(PersistenceHelper::getCascadeQueue($book, PersistenceMode::Persist, $this->orm, true)[0])
		);

		Assert::same(
			[
				$publisher,
				$translator,
				$author,
				$book,
				$publisher->books,
				$translator->getProperty('favoriteAuthor'),
				$translator->favoredBy,
				$translator->books,
				$translator->translatedBooks,
				$translator->tagFollowers,
				$author->getProperty('favoriteAuthor'),
				$author->favoredBy,
				$author->books,
				$author->translatedBooks,
				$author->tagFollowers,
				$book->getProperty('author'),
				$book->getProperty('translator'),
				$book->tags,
				$book->getProperty('publisher'),
			],
			array_values(PersistenceHelper::getCascadeQueue($publisher, PersistenceMode::Persist, $this->orm, true)[0])
		);
	}

}


$test = new PersistenceHelperTest();
$test->run();

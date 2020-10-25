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


class RelationshipOneHasManyRemoveTest extends DataTestCase
{

	public function testRemoveItem(): void
	{
		$author = $this->orm->authors->getByIdChecked(2);
		$book = $this->orm->books->getByIdChecked(3);

		$author->translatedBooks->remove($book);
		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, $author->translatedBooks->count());
		Assert::same(1, $author->translatedBooks->countStored());
	}


	public function testRemoveCollection(): void
	{
		$author = new Author();
		$author->name = 'A';

		$book = new Book();
		$book->title = 'B';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->authors->persistAndFlush($author);

		foreach ($author->books as $innerBook) {
			$this->orm->books->remove($innerBook);
		}

		$this->orm->authors->persistAndFlush($author);
		Assert::same(0, $author->books->count());
	}


	public function testRemoveCollectionAndParent(): void
	{
		$author = new Author();
		$author->name = 'A';

		$book = new Book();
		$book->title = 'B';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->authors->persistAndFlush($author);

		foreach ($author->books as $innerBook) {
			$this->orm->books->remove($innerBook);
		}

		$this->orm->authors->removeAndFlush($author);

		Assert::false($book->isPersisted());
		Assert::false($author->isPersisted());
	}


	public function testRemoveNoCascadeEmptyCollection(): void
	{
		$author = new Author();
		$author->name = 'A';
		$this->orm->authors->persistAndFlush($author);

		$metadata = $author->getMetadata()->getProperty('books');
		Assert::notNull($metadata->relationship);
		$metadata->relationship->cascade['remove'] = false;

		$this->orm->authors->removeAndFlush($author);
		Assert::false($author->isPersisted());
	}
}


$test = new RelationshipOneHasManyRemoveTest();
$test->run();

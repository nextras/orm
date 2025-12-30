<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Orm\Exception\InvalidStateException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Comment;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\Publisher;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryCascadeRemoveTest extends DataTestCase
{
	public function testBasicCascadeRemove(): void
	{
		$author = $this->orm->authors->getByIdChecked(2);

		$bookDiff = new Book();
		$bookDiff->author = $this->orm->authors->getByIdChecked(1);
		$bookDiff->translator = $author;
		$bookDiff->publisher = 1;
		$bookDiff->title = 'Book 5';

		$this->orm->books->persistAndFlush($bookDiff);

		$bookSame = $this->orm->books->getByIdChecked(3);

		$this->orm->authors->removeAndFlush($author); // with cascade

		Assert::true($bookDiff->isPersisted());
		Assert::null($bookDiff->translator);
		Assert::notEqual(null, $bookDiff->author);

		Assert::false($bookSame->isPersisted());
	}


	public function testForeignKeyConstraintRemove(): void
	{
		Assert::throws(function (): void {
			$this->orm->publishers->removeAndFlush($this->orm->publishers->getByIdChecked(1));
		}, InvalidStateException::class, 'Cannot remove NextrasTests\Orm\Publisher::$id=1 because NextrasTests\Orm\Book::$publisher cannot be a null.');
	}


	public function testSingleTableInheritance(): void
	{
		$comment = $this->orm->contents->getByIdChecked(2);
		Assert::type(Comment::class, $comment);
		$thread = $comment->thread;

		$this->orm->remove($thread);
		$this->orm->flush();

		Assert::false($thread->isPersisted());
		Assert::false($comment->isPersisted());
	}


	public function testCascadeWithOneHasOneRelationship(): void
	{
		$ean = new Ean();
		$ean->code = "TEST";
		$book = new Book();
		$book->title = 'Title';
		$book->ean = $ean;
		$book->publisher = new Publisher();
		$book->publisher->name = "Test Publisher";
		$book->author = new Author();
		$book->author->name = "Test";
		$this->orm->persistAndFlush($book);
		$this->orm->removeAndFlush($book);
		Assert::false($book->isPersisted());
	}
}


$test = new RepositoryCascadeRemoveTest();
$test->run();

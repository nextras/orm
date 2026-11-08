<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Relationships\OneHasOne;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasOneOneSidedTest extends DataTestCase
{
	private Book $book;
	private IConnection $connection;


	protected function setUp(): void
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test requires DBAL.');
		}

		$relationship = $this->orm->books->getEntityMetadata()->getProperty('nextPart')->relationship;
		Assert::notNull($relationship);
		$relationship->property = null;
		$this->connection = $this->container->getByType(IConnection::class);
		$this->book = $this->orm->books->getByIdChecked(4);
	}


	public function testReplaceDeletedTarget(): void
	{
		$property = $this->book->getProperty('nextPart');
		assert($property instanceof OneHasOne);
		Assert::false($property->isLoaded());
		$this->connection->query('UPDATE books SET next_part = NULL WHERE id = 4');
		$this->connection->query('DELETE FROM books WHERE id = 3');
		Assert::same(3, $this->book->getRawValue('nextPart'));
		$replacement = $this->orm->books->getByIdChecked(2);

		$this->book->nextPart = $replacement;
		Assert::same($replacement, $this->book->nextPart);
		Assert::same(2, $this->book->getRawValue('nextPart'));
		Assert::true($this->book->isModified('nextPart'));
		$this->orm->books->persistAndFlush($this->book);

		$this->orm->clear();
		Assert::same($this->orm->books->getByIdChecked(2), $this->orm->books->getByIdChecked(4)->nextPart);
		Assert::null($this->orm->books->getById(3));
	}


	public function testClearDeletedTarget(): void
	{
		$property = $this->book->getProperty('nextPart');
		assert($property instanceof OneHasOne);
		Assert::false($property->isLoaded());
		$this->connection->query('UPDATE books SET next_part = NULL WHERE id = 4');
		$this->connection->query('DELETE FROM books WHERE id = 3');

		$this->book->nextPart = null;
		Assert::null($this->book->getRawValue('nextPart'));
		Assert::true($this->book->isModified('nextPart'));
		$this->orm->books->persistAndFlush($this->book);

		$this->orm->clear();
		Assert::null($this->orm->books->getByIdChecked(4)->nextPart);
		Assert::null($this->orm->books->getById(3));
	}


	public function testReplaceDeletedTargetById(): void
	{
		$this->connection->query('UPDATE books SET next_part = NULL WHERE id = 4');
		$this->connection->query('DELETE FROM books WHERE id = 3');

		$this->book->nextPart = 2;
		Assert::same($this->orm->books->getByIdChecked(2), $this->book->nextPart);
		$this->orm->books->persistAndFlush($this->book);

		$this->orm->clear();
		Assert::same($this->orm->books->getByIdChecked(2), $this->orm->books->getByIdChecked(4)->nextPart);
	}


	public function testReplaceDeletedTargetWithNewEntity(): void
	{
		$this->connection->query('UPDATE books SET next_part = NULL WHERE id = 4');
		$this->connection->query('DELETE FROM books WHERE id = 3');
		$replacement = new Book();
		$replacement->title = 'Replacement book';
		$replacement->author = $this->orm->authors->getByIdChecked(1);
		$replacement->publisher = $this->orm->publishers->getByIdChecked(1);

		$this->book->nextPart = $replacement;
		$this->orm->books->persistAndFlush($this->book);
		Assert::true($replacement->isPersisted());
		$replacementId = $replacement->id;

		$this->orm->clear();
		Assert::same(
			$this->orm->books->getByIdChecked($replacementId),
			$this->orm->books->getByIdChecked(4)->nextPart,
		);
		Assert::null($this->orm->books->getById(3));
	}


	public function testClearLoadedDeletedTarget(): void
	{
		$target = $this->orm->books->getByIdChecked(3);
		Assert::same($target, $this->book->nextPart);
		$this->connection->query('UPDATE books SET next_part = NULL WHERE id = 4');
		$this->connection->query('DELETE FROM books WHERE id = 3');

		$this->book->nextPart = null;
		$this->orm->books->persistAndFlush($this->book);

		$this->orm->clear();
		Assert::null($this->orm->books->getByIdChecked(4)->nextPart);
		Assert::null($this->orm->books->getById(3));
	}
}


$test = new RelationshipOneHasOneOneSidedTest();
$test->run();

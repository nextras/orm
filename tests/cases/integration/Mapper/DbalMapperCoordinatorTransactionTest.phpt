<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../../bootstrap.php';


/**
 * persist() lets DbalMapperCoordinator open a transaction, flush() commits it. Committing always closes the innermost
 * level, so a nested transaction opened in between (e.g. by IConnection::transactional()) must not be closed instead.
 */
class DbalMapperCoordinatorTransactionTest extends DataTestCase
{
	protected bool $snapshotQueries = false;

	private IConnection $connection;


	public function setUp()
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY || $this->section === Helper::SECTION_MSSQL) {
			Environment::skip('Test is only for Dbal mapper.');
		}
		$this->connection = $this->container->getByType(IConnection::class);
	}


	public function testFlushInsideTransactionalOpenedAfterPersistThrows(): void
	{
		$author = $this->persistAuthor('Persisted outside');
		Assert::same(1, $this->connection->getTransactionNestedIndex());

		Assert::exception(function (): void {
			$this->connection->transactional(function (): void {
				$this->orm->flush(); // would close the savepoint of transactional()
			});
		}, InvalidStateException::class, 'Cannot flush: the transaction was started by persisting at nesting level 1, but the connection is at level 2.%A%');

		Assert::same(1, $this->connection->getTransactionNestedIndex(), 'the transaction of Orm stays open');

		$this->orm->flush();
		Assert::same(0, $this->connection->getTransactionNestedIndex());
		$this->assertCommitted($author);
	}


	public function testFlushInsideTransactionalAtItsOwnLevel(): void
	{
		$author = null;
		$this->connection->transactional(function () use (&$author): void {
			$author = $this->persistAuthor('Persisted inside');
			$this->orm->flush();
		});

		Assert::same(0, $this->connection->getTransactionNestedIndex());
		assert($author instanceof Author);
		$this->assertCommitted($author);
	}


	public function testFlushAfterTransactionCommittedOutsideOfOrmThrows(): void
	{
		$author = $this->persistAuthor('Committed outside');
		$this->connection->beginTransaction();
		$this->connection->commitTransaction();
		$this->connection->commitTransaction(); // closes the transaction opened by persist()

		Assert::exception(function (): void {
			$this->orm->flush();
		}, InvalidStateException::class, 'Cannot flush: the transaction was started by persisting at nesting level 1, but the connection is at level 0.%A%');

		$this->deleteAuthor($author);
	}


	public function testRollbackInsideTransactionOpenedAfterPersistThrows(): void
	{
		$this->persistAuthor('Rolled back');
		$this->connection->beginTransaction();

		Assert::exception(function (): void {
			$this->container->getByType(DbalMapperCoordinator::class)->rollback();
		}, InvalidStateException::class, 'Cannot roll back: the transaction was started by persisting at nesting level 1, but the connection is at level 2.%A%');

		$this->connection->rollbackTransaction();
		$this->container->getByType(DbalMapperCoordinator::class)->rollback();
		Assert::same(0, $this->connection->getTransactionNestedIndex());
	}


	private function persistAuthor(string $name): Author
	{
		$author = new Author();
		$author->name = $name;
		$this->orm->authors->persist($author);
		return $author;
	}


	private function assertCommitted(Author $author): void
	{
		$other = new Connection($this->connection->getConfig());
		try {
			$row = $other->query('SELECT [name] FROM [authors] WHERE [id] = %i', $author->id)->fetch();
			Assert::same($author->name, $row?->name, 'the row must be visible to another connection, i.e. committed');
		} finally {
			$other->disconnect();
		}
		$this->deleteAuthor($author);
	}


	private function deleteAuthor(Author $author): void
	{
		$this->connection->query('DELETE FROM [authors] WHERE [id] = %i', $author->id);
	}
}


$test = new DbalMapperCoordinatorTransactionTest();
$test->run();

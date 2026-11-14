<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Model;


use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidStateException;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\TestAppModel;
use Tester\Assert;
use Tester\Environment;
use TypeError;

require_once __DIR__ . '/../../../bootstrap.php';


class ModelTransactionalTest extends DataTestCase
{
	protected bool $snapshotQueries = false;


	protected function setUp(): void
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Transaction callbacks require a transactional mapper.');
		}
	}


	public function testReadAndMultipleRepositoriesBelongToTransaction(): void
	{
		$connection = $this->container->getByType(IConnection::class);
		$result = $this->orm->transactional(static function (TestAppModel $orm) use ($connection): int {
			Assert::same(1, $connection->getTransactionNestedIndex());
			$author = $orm->authors->getByIdChecked(1);
			$publisher = $orm->publishers->getByIdChecked(1);
			$author->name = 'Transactional author';
			$publisher->name = 'Transactional publisher';
			$orm->persist($author);
			$orm->persistAndFlush($publisher);
			Assert::same(1, $connection->getTransactionNestedIndex());
			return 42;
		});
		Assert::same(42, $result);
		Assert::same(0, $connection->getTransactionNestedIndex());
		Assert::same('Transactional author', $connection->query('SELECT name FROM authors WHERE id = 1')->fetchField());
		Assert::same('Transactional publisher', $connection->query('SELECT name FROM publishers WHERE publisher_id = 1')->fetchField());
	}


	public function testPersistenceFailureInvalidatesBothEntitiesAndAllowsReuse(): void
	{
		$first = new Author();
		$first->name = 'First';
		$second = new Author();
		$second->name = str_repeat('x', 1000);
		Assert::exception(function () use ($first, $second): void {
			$this->orm->transactional(static function (TestAppModel $orm) use ($first, $second): void {
				$orm->persist($first);
				$orm->persist($second);
			});
		}, QueryException::class);
		Assert::false($first->isAttached());
		Assert::false($first->isPersisted());
		Assert::false($second->isAttached());
		Assert::false($second->isPersisted());
		$connection = $this->container->getByType(IConnection::class);
		Assert::same(0, $connection->getTransactionNestedIndex());
		Assert::same(0, $connection->query('SELECT COUNT(*) FROM authors WHERE name = %s', 'First')->fetchField());

		$fresh = new Author();
		$fresh->name = 'Fresh';
		$this->orm->transactional(static function (TestAppModel $orm) use ($fresh): void {
			$orm->persist($fresh);
		});
		Assert::true($fresh->isPersisted());
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testErrorAfterFlushStillRollsBack(): void
	{
		$author = new Author();
		$author->name = 'Rolled back';
		Assert::exception(function () use ($author): void {
			$this->orm->transactional(static function (TestAppModel $orm) use ($author): void {
				$orm->persistAndFlush($author);
				throw new TypeError('Application failed after flush');
			});
		}, TypeError::class);
		Assert::false($author->isAttached());
		Assert::false($author->isPersisted());
		$connection = $this->container->getByType(IConnection::class);
		Assert::same(0, $connection->query('SELECT COUNT(*) FROM authors WHERE name = %s', 'Rolled back')->fetchField());
	}


	public function testCaughtPersistenceFailureStillAbortsScope(): void
	{
		$author = new Author();
		$author->name = str_repeat('x', 1000);
		Assert::exception(function () use ($author): void {
			$this->orm->transactional(static function (TestAppModel $orm) use ($author): void {
				try {
					$orm->persist($author);
				} catch (QueryException) {
					// Catching the write error does not make partially modified ORM state safe to commit.
				}
			});
		}, QueryException::class);
		Assert::false($author->isAttached());
		Assert::same(0, $this->container->getByType(IConnection::class)->getTransactionNestedIndex());
	}


	public function testRemovedEntityIsInvalidatedAfterFlushAndFailure(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		Assert::exception(function () use ($book): void {
			$this->orm->transactional(static function (TestAppModel $orm) use ($book): void {
				$orm->removeAndFlush($book, false);
				throw new TypeError('Application failed after removal');
			});
		}, TypeError::class);
		Assert::false($book->isAttached());
		Assert::false($book->isPersisted());
		Assert::notSame($book, $this->orm->books->getByIdChecked(1));
	}


	public function testBeforePersistFailureInvalidatesAttemptedEntity(): void
	{
		$author = new Author();
		$author->name = 'Never written';
		$this->orm->authors->onBeforePersist[] = static fn() => throw new TypeError('Before persist failed');
		Assert::exception(function () use ($author): void {
			$this->orm->transactional(static function (TestAppModel $orm) use ($author): void {
				$orm->persist($author);
			});
		}, TypeError::class);
		Assert::false($author->isAttached());
		Assert::false($author->hasValue('name'));
	}


	public function testNestedScopeIsRejectedAndOuterScopeRollsBack(): void
	{
		Assert::exception(function (): void {
			$this->orm->transactional(static function (TestAppModel $orm): void {
				$orm->transactional(static fn() => null);
			});
		}, InvalidStateException::class, 'Nested Model::transactional() calls are not supported.');
		$connection = $this->container->getByType(IConnection::class);
		Assert::same(0, $connection->getTransactionNestedIndex());
	}


	public function testExistingConnectionTransactionIsPreservedOnRejection(): void
	{
		$connection = $this->container->getByType(IConnection::class);
		$connection->beginTransaction();
		try {
			Assert::exception(fn() => $this->orm->transactional(static fn() => null), InvalidStateException::class);
			Assert::same(1, $connection->getTransactionNestedIndex());
		} finally {
			$connection->rollbackTransaction();
		}
	}


	public function testExistingUnflushedWritesArePreservedOnRejection(): void
	{
		$author = new Author();
		$author->name = 'Before transaction';
		$this->orm->persist($author);
		Assert::exception(fn() => $this->orm->transactional(static fn() => null), InvalidStateException::class);
		Assert::true($author->isPersisted());
		Assert::true($author->isAttached());
		$this->orm->flush();
		$connection = $this->container->getByType(IConnection::class);
		Assert::same(1, $connection->query('SELECT COUNT(*) FROM authors WHERE name = %s', 'Before transaction')->fetchField());
	}
}


(new ModelTransactionalTest())->run();

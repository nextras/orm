<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;


use Mockery;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use RuntimeException;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../../../../bootstrap.php';


class DbalMapperCoordinatorTest extends TestCase
{
	public function testSharedImplicitTransactionIsRolledBackOnce(): void
	{
		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('beginTransaction')->once();
		$connection->shouldReceive('rollbackTransaction')->once();
		$coordinator = new DbalMapperCoordinator($connection);
		$coordinator->beginTransaction();
		$coordinator->beginTransaction();
		$coordinator->rollback();
		$coordinator->rollback();
		Assert::same($connection, $coordinator->getConnection());
	}


	public function testFailedRollbackPreventsCoordinatorReuse(): void
	{
		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('beginTransaction')->once();
		$connection->shouldReceive('rollbackTransaction')->once()->andThrow(new RuntimeException('Rollback failed'));
		$coordinator = new DbalMapperCoordinator($connection);
		$coordinator->beginTransaction();
		Assert::exception(fn() => $coordinator->rollback(), RuntimeException::class);
		Assert::exception(fn() => $coordinator->beginTransaction(), InvalidStateException::class);
	}


	public function testManagedFlushDoesNotCommitAndFailedCommitAllowsReuse(): void
	{
		$failure = new RuntimeException('Commit failed');
		$connection = Mockery::mock(IConnection::class);
		$connection->shouldReceive('connect')->once();
		$connection->shouldReceive('getTransactionNestedIndex')->once()->andReturn(0);
		$connection->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $callback) use ($failure): void {
			$callback();
			throw $failure;
		});
		$connection->shouldReceive('beginTransaction')->once();
		$connection->shouldReceive('commitTransaction')->once();
		$coordinator = new DbalMapperCoordinator($connection);
		Assert::same($failure, Assert::exception(function () use ($coordinator): void {
			$coordinator->transactional(static function () use ($coordinator): void {
				$coordinator->beginTransaction();
				$coordinator->flush();
			});
		}, RuntimeException::class));
		$coordinator->beginTransaction();
		$coordinator->flush();
	}


	protected function tearDown(): void
	{
		Mockery::close();
	}
}


(new DbalMapperCoordinatorTest())->run();

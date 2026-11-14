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
		Assert::noError(static function () use ($coordinator): void {
			$coordinator->beginTransaction();
			$coordinator->beginTransaction();
			$coordinator->rollback();
			$coordinator->rollback();
		});
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


	protected function tearDown(): void
	{
		Mockery::close();
	}
}


(new DbalMapperCoordinatorTest())->run();

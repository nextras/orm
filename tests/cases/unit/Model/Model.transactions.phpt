<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Model;


use Mockery;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Exception\RollbackException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\ITransactionalMapper;
use Nextras\Orm\Mapper\ITransactionCoordinator;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use RuntimeException;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

require_once __DIR__ . '/../../../bootstrap.php';


class ModelTransactionsTest extends TestCase
{
	public function testMultipleCoordinatorsAreRejectedBeforeCallback(): void
	{
		$first = Mockery::mock(IMapper::class, ITransactionalMapper::class);
		$first->shouldReceive('getTransactionCoordinator')->once()->andReturn(Mockery::mock(ITransactionCoordinator::class));
		$second = Mockery::mock(IMapper::class, ITransactionalMapper::class);
		$second->shouldReceive('getTransactionCoordinator')->once()->andReturn(Mockery::mock(ITransactionCoordinator::class));
		$firstRepository = Mockery::mock(IRepository::class);
		$firstRepository->shouldReceive('getMapper')->once()->andReturn($first);
		$secondRepository = Mockery::mock(IRepository::class);
		$secondRepository->shouldReceive('getMapper')->once()->andReturn($second);
		$loader = Mockery::mock(IRepositoryLoader::class);
		$loader->shouldReceive('getRepositories')->once()->andReturn([$firstRepository, $secondRepository]);
		$model = new Model($loader, Mockery::mock(MetadataStorage::class));
		Assert::exception(
			fn() => $model->transactional(static fn() => Assert::fail('Callback must not run')),
			NotSupportedException::class,
		);
	}


	public function testUnsupportedMapperIsRejectedBeforeCallback(): void
	{
		$repository = Mockery::mock(IRepository::class);
		$repository->shouldReceive('getMapper')->once()->andReturn(Mockery::mock(IMapper::class));
		$loader = Mockery::mock(IRepositoryLoader::class);
		$loader->shouldReceive('getRepositories')->once()->andReturn([$repository]);
		$model = new Model($loader, Mockery::mock(MetadataStorage::class));
		Assert::exception(
			fn() => $model->transactional(static fn() => Assert::fail('Callback must not run')),
			NotSupportedException::class,
		);
	}


	public function testCommitFailureResetsStateAndPreservesOriginalException(): void
	{
		$failure = new RuntimeException('Commit failed');
		$coordinator = Mockery::mock(ITransactionCoordinator::class);
		$coordinator->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $callback) use ($failure): void {
			$callback();
			throw $failure;
		});
		$mapper = Mockery::mock(IMapper::class, ITransactionalMapper::class);
		$mapper->shouldReceive('getTransactionCoordinator')->once()->andReturn($coordinator);
		$repository = Mockery::mock(IRepository::class);
		$repository->shouldReceive('getMapper')->once()->andReturn($mapper);
		$repository->shouldReceive('doFlush')->once()->andReturn([[], []]);
		$repository->shouldReceive('doClear')->once();
		$loader = Mockery::mock(IRepositoryLoader::class);
		$loader->shouldReceive('getRepositories')->once()->andReturn([$repository]);
		$loader->shouldReceive('getInitializedRepositories')->twice()->andReturn([$repository]);
		$model = new Model($loader, Mockery::mock(MetadataStorage::class));
		Assert::same($failure, Assert::exception(fn() => $model->transactional(static fn() => 42), RuntimeException::class));
	}


	public function testFailedStateResetPreservesCallbackException(): void
	{
		$failure = new TypeError('Callback failed');
		$resetFailure = new RuntimeException('Cleanup failed');
		$coordinator = Mockery::mock(ITransactionCoordinator::class);
		$coordinator->shouldReceive('transactional')->once()->andReturnUsing(static fn(callable $callback) => $callback());
		$mapper = Mockery::mock(IMapper::class, ITransactionalMapper::class);
		$mapper->shouldReceive('getTransactionCoordinator')->once()->andReturn($coordinator);
		$repository = Mockery::mock(IRepository::class);
		$repository->shouldReceive('getMapper')->once()->andReturn($mapper);
		$repository->shouldReceive('doClear')->once()->andThrow($resetFailure);
		$loader = Mockery::mock(IRepositoryLoader::class);
		$loader->shouldReceive('getRepositories')->once()->andReturn([$repository]);
		$loader->shouldReceive('getInitializedRepositories')->once()->andReturn([$repository]);
		$model = new Model($loader, Mockery::mock(MetadataStorage::class));
		$exception = Assert::exception(fn() => $model->transactional(static fn() => throw $failure), RollbackException::class);
		Assert::type(RollbackException::class, $exception);
		Assert::same($failure, $exception->getPrevious());
		Assert::same([$resetFailure], $exception->getCleanupErrors());
	}


	protected function tearDown(): void
	{
		Mockery::close();
	}
}


(new ModelTransactionsTest())->run();

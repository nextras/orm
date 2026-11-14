<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Model;


use Mockery;
use Nextras\Orm\Exception\RollbackException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\IRollbackableMapper;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use RuntimeException;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

require_once __DIR__ . '/../../../bootstrap.php';


class ModelRollbackTest extends TestCase
{
	public function testRollbackAttemptsEveryMapperAndEveryRepositoryReset(): void
	{
		$failure = new RuntimeException('Rollback failed');
		$resetFailure = new TypeError('Cache cleanup failed');
		$first = Mockery::mock(IMapper::class, IRollbackableMapper::class);
		$first->shouldReceive('rollback')->once()->andThrow($failure);
		$second = Mockery::mock(IMapper::class, IRollbackableMapper::class);
		$second->shouldReceive('rollback')->once();
		$firstRepository = Mockery::mock(IRepository::class);
		$firstRepository->shouldReceive('getMapper')->once()->andReturn($first);
		$firstRepository->shouldReceive('doClear')->once()->andThrow($resetFailure);
		$secondRepository = Mockery::mock(IRepository::class);
		$secondRepository->shouldReceive('getMapper')->once()->andReturn($second);
		$secondRepository->shouldReceive('doClear')->once();
		$loader = Mockery::mock(IRepositoryLoader::class);
		$loader->shouldReceive('getInitializedRepositories')->twice()->andReturn([$firstRepository, $secondRepository]);
		$model = new Model($loader, Mockery::mock(MetadataStorage::class));
		$exception = Assert::exception(fn() => $model->rollback(), RollbackException::class);
		Assert::type(RollbackException::class, $exception);
		Assert::same($failure, $exception->getPrevious());
		Assert::same([$failure, $resetFailure], $exception->getCleanupErrors());
	}


	protected function tearDown(): void
	{
		Mockery::close();
	}
}


(new ModelRollbackTest())->run();

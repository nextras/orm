<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\AbstractEntity;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Repository\IRepository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class AbstractEntityRepositoryTest extends TestCase
{

	public function testAttach()
	{
		$repository = Mockery::mock(IRepository::class);
		$metadata = Mockery::mock(EntityMetadata::class);

		/** @var IEntity $entity */
		$entity = Mockery::mock(AbstractEntity::class)->makePartial();
		$entity->onAttach($repository, $metadata);

		Assert::same($repository, $entity->getRepository());
	}


	public function testDoubleAttach()
	{
		$repository = Mockery::mock(IRepository::class);
		$metadata = Mockery::mock(EntityMetadata::class);

		/** @var IEntity $entity */
		$entity = Mockery::mock(AbstractEntity::class)->makePartial();
		$entity->onAttach($repository, $metadata);

		Assert::throws(function () use ($entity, $metadata) {
			$entity->onAttach(Mockery::mock(IRepository::class), $metadata);
		}, InvalidStateException::class, 'Entity is already attached.');

		Assert::same($repository, $entity->getRepository());
	}


	public function testAfterRemove()
	{
		$repository = Mockery::mock(IRepository::class);
		$metadata = Mockery::mock(EntityMetadata::class);

		/** @var IEntity $entity */
		$entity = Mockery::mock(AbstractEntity::class)->makePartial();
		$entity->onAttach($repository, $metadata);
		Assert::same($repository, $entity->getRepository());

		$entity->onAfterRemove();
		Assert::false($entity->isAttached());
		Assert::throws(function () use ($entity) {
			$entity->getRepository();
		}, InvalidStateException::class);
	}

}


$test = new AbstractEntityRepositoryTest($dic);
$test->run();

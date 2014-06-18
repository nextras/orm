<?php

namespace Nextras\Orm\Tests\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryEntityFragmentTestCase extends TestCase
{

	public function testAttach()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$entity->fireEvent('onAttach', [$repository]);

		Assert::same($repository, $entity->getRepository());
	}


	public function testDoubleAttach()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$entity->fireEvent('onAttach', [$repository]);

		Assert::throws(function() use ($entity) {
			$entity->fireEvent('onAttach', [Mockery::mock('Nextras\Orm\Repository\IRepository')]);
		}, 'Nextras\Orm\InvalidStateException', 'Entity is already attached.');

		Assert::same($repository, $entity->getRepository());
	}


	public function testAfterRemove()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$entity->fireEvent('onAttach', [$repository]);
		Assert::same($repository, $entity->getRepository());

		$entity->fireEvent('onAfterRemove');
		Assert::null($entity->getRepository(FALSE));
		Assert::throws(function() use ($entity) {
			$entity->getRepository();
		}, 'Nextras\Orm\InvalidStateException', 'Entity is not attached to repository.');
	}


	public function testCloning()
	{
		$cloned = NULL;

		/** @var IEntity $entity */
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$repository->shouldReceive('attach')->andReturnUsing(function(IEntity $entity) use (& $cloned) { $cloned = $entity; } );

		$entity->fireEvent('onAttach', [$repository]);
		$entity = clone $entity;

		Assert::same($entity, $cloned);
	}

}


$test = new RepositoryEntityFragmentTestCase($dic);
$test->run();

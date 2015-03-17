<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\IEntity;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class AbstractEntityRepositoryTest extends TestCase
{

	public function testAttach()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');
		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\AbstractEntity')->makePartial();
		$entity->fireEvent('onAttach', [$repository, $metadata]);

		Assert::same($repository, $entity->getRepository());
	}


	public function testDoubleAttach()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');
		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\AbstractEntity')->makePartial();
		$entity->fireEvent('onAttach', [$repository, $metadata]);

		Assert::throws(function() use ($entity, $metadata) {
			$entity->fireEvent('onAttach', [Mockery::mock('Nextras\Orm\Repository\IRepository'), $metadata]);
		}, 'Nextras\Orm\InvalidStateException', 'Entity is already attached.');

		Assert::same($repository, $entity->getRepository());
	}


	public function testAfterRemove()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');
		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\AbstractEntity')->makePartial();
		$entity->fireEvent('onAttach', [$repository, $metadata]);
		Assert::same($repository, $entity->getRepository());

		$entity->fireEvent('onAfterRemove');
		Assert::null($entity->getRepository(FALSE));
		Assert::throws(function() use ($entity) {
			$entity->getRepository();
		}, 'Nextras\Orm\InvalidStateException', 'Entity is not attached to repository.');
	}

}


$test = new AbstractEntityRepositoryTest($dic);
$test->run();

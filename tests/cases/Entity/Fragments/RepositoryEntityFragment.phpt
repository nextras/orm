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
		$bookRepository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$entity->fireEvent('onAttach', array($bookRepository));

		Assert::same($bookRepository, $entity->getRepository());
	}


	public function testDoubleAttach()
	{
		$bookRepository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$entity->fireEvent('onAttach', array($bookRepository));

		Assert::throws(function() use ($entity) {
			$entity->fireEvent('onAttach', [Mockery::mock('Nextras\Orm\Repository\IRepository')]);
		}, 'Nextras\Orm\InvalidStateException', 'Entity is already attached.');

		Assert::same($bookRepository, $entity->getRepository());
	}


	public function testAfterRemove()
	{
		$bookRepository = Mockery::mock('Nextras\Orm\Repository\IRepository');

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Entity\Fragments\RepositoryEntityFragment')->makePartial();
		$entity->fireEvent('onAttach', array($bookRepository));
		Assert::same($bookRepository, $entity->getRepository());

		$entity->fireEvent('onAfterRemove');
		Assert::null($entity->getRepository(FALSE));
		Assert::throws(function() use ($entity) {
			$entity->getRepository();
		}, 'Nextras\Orm\InvalidStateException', 'Entity is not attached to repository.');
	}

}


$test = new RepositoryEntityFragmentTestCase($dic);
$test->run();

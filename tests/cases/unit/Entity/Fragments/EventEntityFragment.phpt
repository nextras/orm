<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\Fragments\EventEntityFragment;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


abstract class EventTestEntity extends EventEntityFragment
{
	public $called;
	protected function onCreate() { parent::onCreate(); $this->called = 'create'; }
	protected function onBeforePersist() { parent::onBeforePersist(); $this->called = 'bp'; }
	protected function onAfterPersist() { parent::onAfterPersist(); $this->called = 'ap'; }
	protected function onBeforeInsert() { parent::onBeforeInsert(); $this->called = 'bi'; }
	protected function onAfterInsert() { parent::onAfterInsert(); $this->called = 'ai'; }
	protected function onBeforeUpdate() { parent::onBeforeUpdate(); $this->called = 'bu'; }
	protected function onAfterUpdate() { parent::onAfterUpdate(); $this->called = 'au'; }
}
abstract class EventTestEntity2 extends  EventEntityFragment
{
	public $called;
	protected function onAfterPersist()
	{
		$this->called = 'ap';
	}
}


class EventEntityFragmentTest extends TestCase
{

	public function testCreateEventMethod()
	{
		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity')->makePartial();
		$entity->__construct();

		Assert::equal('create', $entity->called);
	}


	public function testCallingEventMethod()
	{
		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity')->makePartial();

		$entity->fireEvent('onBeforePersist');
		Assert::equal('bp', $entity->called);
		$entity->fireEvent('onAfterPersist');
		Assert::equal('ap', $entity->called);
		$entity->fireEvent('onBeforeInsert');
		Assert::equal('bi', $entity->called);
		$entity->fireEvent('onAfterInsert');
		Assert::equal('ai', $entity->called);
		$entity->fireEvent('onBeforeUpdate');
		Assert::equal('bu', $entity->called);
		$entity->fireEvent('onAfterUpdate');
		Assert::equal('au', $entity->called);
	}


	public function testMissingEventMethodCall()
	{
		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity2')->makePartial();

		Assert::throws(function() use ($entity) {
			$entity->fireEvent('onAfterPersist');
		}, 'Nextras\Orm\InvalidStateException', "Event 'onAfterPersist' was not correctly propagate to overwritten methods.");
	}


	public function testWrongEvent()
	{
		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity')->makePartial();

		Assert::throws(function() use ($entity) {
			$entity->fireEvent('onWrongEventName');
		}, 'Nextras\Orm\InvalidArgumentException', "Event 'onWrongEventName' does not exist.");
	}


	public function testUndefinedPropertyException()
	{
		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity')->makePartial();

		Assert::throws(function() use ($entity) {
			$entity->undefined;
		}, 'Nextras\Orm\MemberAccessException', "Undefined 'undefined' property.");

		Assert::throws(function() use ($entity) {
			$entity->undefined = 'test';
		}, 'Nextras\Orm\MemberAccessException', "Undefined 'undefined' property.");

		Assert::throws(function() use ($entity) {
			unset($entity->undefined);
		}, 'Nextras\Orm\MemberAccessException', "Undefined 'undefined' property.");

		Assert::throws(function() use ($entity) {
			isset($entity->undefined);
		}, 'Nextras\Orm\MemberAccessException', "Undefined 'undefined' property.");
	}

}


$test = new EventEntityFragmentTest($dic);
$test->run();

<?php

namespace Nextras\Orm\Tests\Entity\Fragments;

use Mockery;
use Nextras\Orm\Entity\Fragments\EventEntityFragment;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


abstract class EventTestEntity extends EventEntityFragment
{
	public $called;
	protected function onCreate()
	{
		parent::onCreate();
		$this->called = 'create';
	}
	protected function onBeforePersist()
	{
		parent::onBeforePersist();
		$this->called = 'bf';
	}
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
		Assert::equal('bf', $entity->called);
	}


	public function testCallingEventCallback()
	{
		$test = NULL;

		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity')->makePartial();
		$entity->onBeforeRemove[] = function() use (& $test) {
			$test = 'br';
		};

		$entity->fireEvent('onBeforeRemove');
		Assert::equal('br', $test);
	}


	public function testMissingEventMethodCall()
	{
		/** @var IEntity $entity */
		$entity = Mockery::mock('Nextras\Orm\Tests\Entity\Fragments\EventTestEntity')->makePartial();

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

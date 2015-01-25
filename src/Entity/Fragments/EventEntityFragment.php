<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Fragments;

use Nette\Utils\ObjectMixin;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Model;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\MemberAccessException;


abstract class EventEntityFragment implements IEntity
{
	/** @var string */
	private $eventCheck;


	public function __construct()
	{
		$this->fireEvent('onCreate');
	}


	public function fireEvent($method, $args = [])
	{
		if (!method_exists($this, $method)) {
			throw new InvalidArgumentException("Event '$method' does not exist.");
		}

		$this->eventCheck = FALSE;
		call_user_func_array([$this, $method], $args);
		if (!$this->eventCheck) {
			throw new InvalidStateException("Event '$method' was not correctly propagate to overwritten methods.");
		}
	}


	public function & __get($key)
	{
		throw new MemberAccessException("Undefined '$key' property.");
	}


	public function __set($key, $name)
	{
		throw new MemberAccessException("Undefined '$key' property.");
	}


	public function __isset($key)
	{
		throw new MemberAccessException("Undefined '$key' property.");
	}


	public function __unset($key)
	{
		throw new MemberAccessException("Undefined '$key' property.");
	}


	protected function onCreate()
	{
		$this->eventCheck = TRUE;
	}


	protected  function onLoad(array $data)
	{
		$this->eventCheck = TRUE;
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		$this->eventCheck = TRUE;
	}


	protected function onDetach()
	{
		$this->eventCheck = TRUE;
	}


	protected function onPersist($id)
	{
		$this->eventCheck = TRUE;
	}


	protected function onBeforePersist()
	{
		$this->eventCheck = TRUE;
	}


	protected function onAfterPersist()
	{
		$this->eventCheck = TRUE;
	}


	protected function onBeforeInsert()
	{
		$this->eventCheck = TRUE;
	}


	protected function onAfterInsert()
	{
		$this->eventCheck = TRUE;
	}


	protected function onBeforeUpdate()
	{
		$this->eventCheck = TRUE;
	}


	protected function onAfterUpdate()
	{
		$this->eventCheck = TRUE;
	}


	protected function onBeforeRemove()
	{
		$this->eventCheck = TRUE;
	}


	protected function onAfterRemove()
	{
		$this->eventCheck = TRUE;
	}

}

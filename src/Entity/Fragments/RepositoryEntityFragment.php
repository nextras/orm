<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Fragments;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidStateException;


abstract class RepositoryEntityFragment extends EventEntityFragment implements IEntity
{
	/** @var IRepository */
	private $repository;

	/** @var mixed */
	private $persistedId = NULL;


	/**
	 * Return model.
	 * @param  bool $need
	 * @return IModel|NULL
	 */
	public function getModel($need = TRUE)
	{
		$repository = $this->getRepository($need);
		return $repository ? $repository->getModel($need) : NULL;
	}


	/**
	 * Returns repository to which is entity attached.
	 * @param  bool $need
	 * @return IRepository|NULL
	 * @throws InvalidStateException
	 */
	public function getRepository($need = TRUE)
	{
		if ($this->repository === NULL && $need) {
			throw new InvalidStateException('Entity is not attached to repository.');
		}

		return $this->repository;
	}


	public function isPersisted()
	{
		return $this->persistedId !== NULL;
	}


	public function getPersistedId()
	{
		return $this->persistedId;
	}


	public function isAttached()
	{
		return $this->repository !== NULL;
	}


	public function __clone()
	{
		if ($repository = $this->repository) {
			$this->repository = NULL;
			$repository->attach($this);
		}
		$this->persistedId = NULL;
	}


	public function serialize()
	{
		return [
			'persistedId' => $this->persistedId,
		];
	}


	public function unserialize($unserialized)
	{
		$this->persistedId = $unserialized['persistedId'];
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		parent::onAttach($repository, $metadata);
		$this->attach($repository);
	}


	protected function onLoad(IRepository $repository, EntityMetadata $metadata, array $data)
	{
		parent::onLoad($repository, $metadata, $data);
		$this->repository = $repository;
		$this->persistedId = TRUE; // id getter see entity already as persisted
		$this->persistedId = $this->getValue('id');
	}


	protected function onPersist($id)
	{
		parent::onPersist($id);
		$this->persistedId = $this->getValue('id');
	}


	protected function onAfterRemove()
	{
		call_user_func_array(['parent', 'onAfterRemove'], func_get_args());
		$this->repository = NULL;
		$this->persistedId = NULL;
	}


	private function attach(IRepository $repository)
	{
		if ($this->repository !== NULL && $this->repository !== $repository) {
			throw new InvalidStateException('Entity is already attached.');
		}

		$this->repository = $repository;
	}

}

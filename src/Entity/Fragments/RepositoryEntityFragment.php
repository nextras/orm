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

	/** @var bool */
	private $isPersisted = FALSE;


	/**
	 * Return model.
	 * @param  bool
	 * @return IModel|NULL
	 */
	public function getModel($need = TRUE)
	{
		$repository = $this->getRepository($need);
		return $repository ? $repository->getModel($need) : NULL;
	}


	/**
	 * Returns repository to which is entity attached.
	 * @param  bool
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
		return $this->isPersisted;
	}


	public function __clone()
	{
		if ($repository = $this->repository) {
			$this->repository = NULL;
			$repository->attach($this);
		}
		$this->isPersisted = FALSE;
	}


	protected function onAttach(IRepository $repository)
	{
		parent::onAttach($repository);
		$this->attach($repository);
	}


	protected function onLoad(IRepository $repository, EntityMetadata $metadata, array $data)
	{
		parent::onLoad($repository, $metadata, $data);
		$this->repository = $repository;
		$this->isPersisted = TRUE;
	}


	protected function onAfterRemove()
	{
		call_user_func_array(['parent', 'onAfterRemove'], func_get_args());
		$this->repository = NULL;
		$this->isPersisted = FALSE;
	}


	private function attach(IRepository $repository)
	{
		if ($this->repository !== NULL && $this->repository !== $repository) {
			throw new InvalidStateException('Entity is already attached.');
		}

		$this->repository = $repository;
	}

}

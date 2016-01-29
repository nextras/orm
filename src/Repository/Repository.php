<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nette\Object;
use Nette\Utils\ObjectMixin;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;


abstract class Repository extends Object implements IRepository
{
	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforePersist = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterPersist = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforeInsert = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterInsert = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforeUpdate = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterUpdate = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onBeforeRemove = [];

	/** @var array of callbacks with (IEntity $entity) arguments */
	public $onAfterRemove = [];

	/** @var array of callbacks with (IEntity[] $persisted, IEntity[] $removed) arguments */
	public $onFlush = [];

	/** @var IMapper */
	protected $mapper;

	/** @var string */
	protected $entityClassName;

	/** @var IModel */
	private $model;

	/** @var IdentityMap */
	private $identityMap;

	/** @var array */
	private $proxyMethods;

	/** @var MetadataStorage */
	private $metadataStorage;

	/** @var array */
	private $entitiesToFlush = [[], []];

	/** @var IDependencyProvider */
	private $dependencyProvider;


	/**
	 * @param  IMapper              $mapper
	 * @param  IDependencyProvider  $dependencyProvider
	 */
	public function __construct(IMapper $mapper, IDependencyProvider $dependencyProvider = null)
	{
		$this->mapper = $mapper;
		$this->mapper->setRepository($this);
		$this->identityMap = new IdentityMap($this, $dependencyProvider);
		$this->dependencyProvider = $dependencyProvider;

		$annotations = $this->reflection->getAnnotations();
		if (isset($annotations['method'])) {
			foreach ((array) $annotations['method'] as $annotation) {
				$this->proxyMethods[strtolower(preg_replace('#^[^\s]+\s+(\w+)\(.*\).*$#', '$1', $annotation))] = true;
			}
		}
	}


	/** @inheritdoc */
	public function getModel($need = true)
	{
		if ($this->model === null && $need) {
			throw new InvalidStateException('Repository is not attached to model.');
		}

		return $this->model;
	}


	/** @inheritdoc */
	public function setModel(IModel $model)
	{
		if ($this->model && $this->model !== $model) {
			throw new InvalidStateException('Repository is already attached.');
		}

		$this->model = $model;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	/** @inheritdoc */
	public function getMapper()
	{
		if (!$this->mapper) {
			throw new InvalidStateException('Repository does not have injected any mapper.');
		}

		return $this->mapper;
	}


	/** @inheritdoc */
	public function getBy(array $conds)
	{
		return call_user_func_array([$this->findAll(), 'getBy'], func_get_args());
	}


	/** @inheritdoc */
	public function getById($id)
	{
		if ($id === null) {
			return null;
		} elseif ($id instanceof IEntity) {
			$id = $id->getValue('id');
		} elseif (!(is_scalar($id) || is_array($id))) {
			throw new InvalidArgumentException('Primary key value has to be a scalar.');
		}

		$entity = $this->identityMap->getById($id);
		if ($entity === false) { // entity was removed
			return null;
		} elseif ($entity instanceof IEntity) {
			return $entity;
		}

		$entity = $this->findAll()->getBy(['id' => $id]);
		if ($entity === null) {
			$this->identityMap->remove($id);
		}

		return $entity;
	}


	/** @inheritdoc */
	public function findAll()
	{
		return $this->getMapper()->findAll();
	}


	/** @inheritdoc */
	public function findBy(array $conds)
	{
		return call_user_func_array([$this->findAll(), 'findBy'], func_get_args());
	}


	/** @inheritdoc */
	public function findById($ids)
	{
		return call_user_func_array([$this->findAll(), 'findBy'], [['id' => $ids]]);
	}


	/** @inheritdoc */
	public function attach(IEntity $entity)
	{
		if (!$entity->isAttached()) {
			$entity->fireEvent('onAttach', [$this, $this->metadataStorage->get(get_class($entity))]);
			if ($this->dependencyProvider) {
				$this->dependencyProvider->injectDependencies($entity);
			}
		}
	}


	/** @inheritdoc */
	public function detach(IEntity $entity)
	{
		if ($entity->isAttached()) {
			$entity->fireEvent('onDetach');
		}
	}


	/** @inheritdoc */
	public function hydrateEntity(array $data)
	{
		return $this->identityMap->create($data);
	}


	/** @inheritdoc */
	public function getEntityMetadata()
	{
		return $this->metadataStorage->get(static::getEntityClassNames()[0]);
	}


	/** @inheritdoc */
	public function getEntityClassName(array $data)
	{
		if (!$this->entityClassName) {
			$this->entityClassName = static::getEntityClassNames()[0];
		}

		return $this->entityClassName;
	}


	/** @inheritdoc */
	public function persist(IEntity $entity, $withCascade = true)
	{
		$this->identityMap->check($entity);
		return $this->model->persist($entity, $withCascade);
	}


	/** @inheritdoc */
	public function doPersist(IEntity $entity)
	{
		if (!$entity->isModified()) {
			return;
		}

		$isPersisted = $entity->isPersisted();
		$this->doFireEvent($entity, $isPersisted ? 'onBeforeUpdate' : 'onBeforeInsert');

		$isPersisted && $this->identityMap->remove($entity->getPersistedId()); // id can change in composite key
		$id = $this->mapper->persist($entity);
		$entity->fireEvent('onPersist', [$id]);
		$this->identityMap->add($entity);
		$this->entitiesToFlush[0][] = $entity;

		$this->doFireEvent($entity, $isPersisted ? 'onAfterUpdate' : 'onAfterInsert');
		$this->doFireEvent($entity, 'onAfterPersist');
	}


	/** @inheritdoc */
	public function remove($entity, $withCascade = true)
	{
		$entity = $entity instanceof IEntity ? $entity : $this->getById($entity);
		$this->identityMap->check($entity);
		return $this->model->remove($entity, $withCascade);
	}


	/** @inheritdoc */
	public function doRemove(IEntity $entity)
	{
		$this->detach($entity);
		if (!$entity->isPersisted()) {
			return;
		}

		$this->mapper->remove($entity);
		$this->identityMap->remove($entity->getPersistedId());
		$this->entitiesToFlush[1][] = $entity;
		$this->doFireEvent($entity, 'onAfterRemove');
	}


	/** @inheritdoc */
	public function flush()
	{
		$this->getModel()->flush();
	}


	/** @inheritdoc */
	public function persistAndFlush(IEntity $entity, $withCascade = true)
	{
		$this->persist($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	/** @inheritdoc */
	public function removeAndFlush($entity, $withCascade = true)
	{
		$this->remove($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	/** @inheritdoc */
	public function doFlush()
	{
		$this->mapper->flush();
		$this->onFlush($this->entitiesToFlush[0], $this->entitiesToFlush[1]);
		$entities = $this->entitiesToFlush;
		$this->entitiesToFlush = [[], []];
		return $entities;
	}


	/** @inheritdoc */
	public function doClearIdentityMap($areYouSure = null)
	{
		if ($areYouSure !== IModel::I_KNOW_WHAT_I_AM_DOING) {
			throw new LogicException('Do not call this method directly. Use IModel::clearIdentityMapAndCaches().');
		}

		$this->identityMap->destroyAllEntities();
		$this->mapper->clearCollectionCache();
	}


	/** @inheritdoc */
	public function doFireEvent(IEntity $entity, $event)
	{
		if (!property_exists($this, $event)) {
			throw new InvalidArgumentException("Event '{$event}' is not defined.");
		}

		$entity->fireEvent($event);
		ObjectMixin::call($this, $event, [$entity]);
	}


	public function __call($method, $args)
	{
		if (isset($this->proxyMethods[strtolower($method)])) {
			$result = call_user_func_array([$this->mapper, $method], $args);
			if (!($result instanceof ICollection || $result instanceof IEntity || $result === null)) {
				$result = $this->mapper->toCollection($result);
			}
			return $result;

		} else {
			return parent::__call($method, $args);
		}
	}
}

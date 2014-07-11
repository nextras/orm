<?php

/**
 * This file is part of the Nextras\ORM library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Repository;

use Inflect\Inflect;
use Nette\Object;
use Nextras\Orm\DI\EntityDependencyProvider;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


abstract class Repository extends Object implements IRepository
{
	/** @var IMapper */
	protected $mapper;

	/** @var array */
	protected $entityClassName;

	/** @var IModel */
	private $model;

	/** @var IdentityMap */
	private $identityMap;

	/** @var array */
	private $proxyMethods;

	/** @var array */
	private $isPersisting = [];

	/** @var EntityDependencyProvider */
	private $dependencyProvider;


	/**
	 * @param  IMapper|NULL             $mapper
	 * @param  EntityDependencyProvider $dependencyProvider
	 * @throws InvalidArgumentException
	 */
	public function __construct(IMapper $mapper, EntityDependencyProvider $dependencyProvider = NULL)
	{
		$this->mapper = $mapper;
		$this->mapper->setRepository($this);
		$this->identityMap = new IdentityMap($this);

		$annotations = $this->reflection->getAnnotations();
		if (isset($annotations['method'])) {
			foreach ((array) $annotations['method'] as $annotation) {
				$this->proxyMethods[strtolower(preg_replace('#^[^\s]+\s+(\w+)\(.*\).*$#', '$1', $annotation))] = TRUE;
			}
		}
		$this->dependencyProvider = $dependencyProvider;
	}


	public function getModel($need = TRUE)
	{
		if ($this->model === NULL && $need) {
			throw new InvalidStateException('Repository is not attached to model.');
		}

		return $this->model;
	}


	public function onModelAttach(IModel $model)
	{
		if ($this->model && $this->model !== $model) {
			throw new InvalidStateException('Repository is already attached.');
		}

		$this->model = $model;
	}


	public function getMapper()
	{
		if (!$this->mapper) {
			throw new InvalidStateException('Repository does not have injected any mapper.');
		}

		return $this->mapper;
	}


	public function getById($id)
	{
		if ($id === NULL) {
			return NULL;
		} elseif ($id instanceof IEntity) {
			$id = $id->id;
		} elseif (!(is_scalar($id) || is_array($id))) {
			throw new InvalidArgumentException('Primary key value has to be a scalar.');
		}

		$entity = $this->identityMap->getById($id);
		if ($entity === FALSE) {
			return NULL;
		} elseif ($entity instanceof IEntity) {
			return $entity;
		}

		$entity = $this->findAll()->getBy(['id' => $id]);
		if ($entity === NULL) {
			$this->identityMap->remove($id);
		}

		return $entity;
	}


	/**
	 * @param  mixed    $ids
	 * @return ICollection
	 */
	public function findById($ids)
	{
		return call_user_func_array([$this->findAll(), 'findBy'], [['id' => $ids]]);
	}


	/**
	 * @return ICollection
	 */
	public function findBy()
	{
		return call_user_func_array([$this->findAll(), 'findBy'], func_get_args());
	}


	public function attach(IEntity $entity)
	{
		if (!$entity->getRepository(FALSE)) {
			$this->identityMap->attach($entity);
			if ($this->dependencyProvider) {
				$this->dependencyProvider->injectDependencies($entity);
			}
		}
	}


	public function hydrateEntity(array $data)
	{
		return $this->identityMap->create($data);
	}


	public static function getEntityClassNames()
	{
		$class = substr(get_called_class(), 0, -10);
		return [Inflect::singularize($class)];
	}


	public function getEntityClassName(array $data)
	{
		if (!$this->entityClassName) {
			$this->entityClassName = static::getEntityClassNames()[0];
		}

		return $this->entityClassName;
	}


	public function findAll()
	{
		return $this->getMapper()->findAll();
	}


	public function persist(IEntity $entity, $recursive = TRUE)
	{
		if (isset($this->isPersisting[spl_object_hash($entity)])) {
			return $entity;
		}

		$this->isPersisting[spl_object_hash($entity)] = TRUE;

		$this->attach($entity);
		$relationships = [];

		if ($recursive) {
			foreach ($entity->toArray(IEntity::TO_ARRAY_LOADED_RELATIONSHIP_AS_IS) as $k => $v) {
				if ($v instanceof IEntity) {
					$this->model->getRepositoryForEntity($v)->persist($v);
				} elseif ($v instanceof IRelationshipCollection) {
					$relationships[] = $v;
				}
			}
		}

		if ($entity->isModified()) {
			$id = $this->mapper->persist($entity);
			$entity->fireEvent('onPersist', [$id]);
		}

		foreach ($relationships as $relationship) {
			$relationship->persist($recursive);
		}

		unset($this->isPersisting[spl_object_hash($entity)]);
		return $entity;
	}


	public function remove($entity)
	{
		$entity = $entity instanceof IEntity ? $entity : $this->getById($entity);
		// $this->identityMap->check($entity);

		if ($entity->isPersisted() || $entity->getRepository(FALSE)) {
			$entity->fireEvent('onBeforeRemove');

			if (isset($entity->id)) {
				$this->mapper->remove($entity);
				$this->identityMap->remove($entity->id);
			}

			$this->identityMap->detach($entity);
			$entity->fireEvent('onAfterRemove');
		}

		return $entity;
	}


	public function flush()
	{
		$this->mapper->flush();
	}


	public function persistAndFlush(IEntity $entity, $recursive = TRUE)
	{
		$this->persist($entity, $recursive);
		$this->flush();
		return $entity;
	}


	public function removeAndFlush(IEntity $entity)
	{
		$this->remove($entity);
		$this->flush();
		return $entity;
	}


	public function __call($method, $args)
	{
		if (isset($this->proxyMethods[strtolower($method)])) {
			if (substr($method, 0, 5) === 'getBy' || substr($method, 0, 6) === 'findBy') {
				return call_user_func_array([$this->findAll(), $method], $args);
			}

			$result = call_user_func_array([$this->mapper, $method], $args);

			if (!($result instanceof ICollection || $result instanceof IEntity)) {
				$result = $this->mapper->toCollection($result);
			}

			return $result;

		} else {
			return parent::__call($method, $args);
		}
	}

}

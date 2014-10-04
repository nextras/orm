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
use Nette\Utils\ObjectMixin;
use Nextras\Orm\DI\EntityDependencyProvider;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\PersistanceHelper;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


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
	private $isProcessing = [];

	/** @var MetadataStorage */
	private $metadataStorage;


	/**
	 * @param  IMapper                  $mapper
	 * @param  EntityDependencyProvider $dependencyProvider
	 */
	public function __construct(IMapper $mapper, EntityDependencyProvider $dependencyProvider = NULL)
	{
		$this->mapper = $mapper;
		$this->mapper->setRepository($this);
		$this->identityMap = new IdentityMap($this, $dependencyProvider);

		$annotations = $this->reflection->getAnnotations();
		if (isset($annotations['method'])) {
			foreach ((array) $annotations['method'] as $annotation) {
				$this->proxyMethods[strtolower(preg_replace('#^[^\s]+\s+(\w+)\(.*\).*$#', '$1', $annotation))] = TRUE;
			}
		}
	}


	public function getModel($need = TRUE)
	{
		if ($this->model === NULL && $need) {
			throw new InvalidStateException('Repository is not attached to model.');
		}

		return $this->model;
	}


	public function setModel(IModel $model)
	{
		if ($this->model && $this->model !== $model) {
			throw new InvalidStateException('Repository is already attached.');
		}

		$this->model = $model;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	public function getMapper()
	{
		if (!$this->mapper) {
			throw new InvalidStateException('Repository does not have injected any mapper.');
		}

		return $this->mapper;
	}


	public function getBy(array $conds)
	{
		return call_user_func_array([$this->findAll(), 'getBy'], func_get_args());
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
		if ($entity === FALSE) { // entity was removed
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


	public function findAll()
	{
		return $this->getMapper()->findAll();
	}


	public function findBy(array $conds)
	{
		return call_user_func_array([$this->findAll(), 'findBy'], func_get_args());
	}


	public function findById($ids)
	{
		return call_user_func_array([$this->findAll(), 'findBy'], [['id' => $ids]]);
	}


	public function attach(IEntity $entity)
	{
		if (!$entity->getRepository(FALSE)) {
			$this->identityMap->attach($entity);
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


	public function getEntityMetadata()
	{
		return $this->metadataStorage->get(static::getEntityClassNames()[0]);
	}


	public function getEntityClassName(array $data)
	{
		if (!$this->entityClassName) {
			$this->entityClassName = static::getEntityClassNames()[0];
		}

		return $this->entityClassName;
	}


	public function persist(IEntity $entity, $recursive = TRUE)
	{
		$this->identityMap->check($entity);
		if (isset($this->isProcessing[spl_object_hash($entity)])) {
			return $entity;
		}

		$this->isProcessing[spl_object_hash($entity)] = TRUE;
		$this->attach($entity);

		if ($recursive) {
			list($prePersist, $postPersist) = PersistanceHelper::getLoadedRelationships($entity);
			foreach ($prePersist as $value) {
				$this->model->getRepositoryForEntity($value)->persist($value);
			}
		}

		if ($entity->isModified()) {
			$isPersisted = $entity->isPersisted();
			$this->fireEvent($entity, 'onBeforePersist');
			$this->fireEvent($entity, $isPersisted ? 'onBeforeUpdate' : 'onBeforeInsert');

			$id = $this->mapper->persist($entity);
			$entity->fireEvent('onPersist', [$id]);

			$this->fireEvent($entity, $isPersisted ? 'onAfterUpdate' : 'onAfterInsert');
			$this->fireEvent($entity, 'onAfterPersist');
		}

		if (isset($postPersist)) {
			foreach ($postPersist as $value) {
				if ($value instanceof IEntity) {
					$this->model->getRepositoryForEntity($value)->persist($value);
				} elseif ($value instanceof IRelationshipCollection) {
					$value->persist($recursive);
				}
			}
		}

		unset($this->isProcessing[spl_object_hash($entity)]);
		return $entity;
	}


	public function remove($entity, $recursive = FALSE)
	{
		$entity = $entity instanceof IEntity ? $entity : $this->getById($entity);
		$this->identityMap->check($entity);

		if (isset($this->isProcessing[spl_object_hash($entity)])) {
			return $entity;
		}

		$this->isProcessing[spl_object_hash($entity)] = TRUE;
		$this->fireEvent($entity, 'onBeforeRemove');

		foreach ($entity->getMetadata()->getProperties() as $property) {
			if ($property->relationshipType) {
				if (in_array($property->relationshipType, [
					PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE,
					PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE,
					PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED,
				])) {
					$entity->getProperty($property->name)->set(NULL, TRUE);

				} elseif ($property->relationshipType === PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY)	{
					$entity->getValue($property->name)->set([]);

				} else {
					$reverseRepository = $this->model->getRepository($property->relationshipRepository);
					$reverseProperty = $reverseRepository->getEntityMetadata()->getProperty($property->relationshipProperty);

					if ($reverseProperty->isNullable || !$recursive) {
						$entity->getValue($property->name)->set([]);
					} else {
						foreach ($entity->getValue($property->name) as $reverseEntity) {
							$reverseRepository->remove($reverseEntity, $recursive);
						}
					}
				}
			}
		}

		if ($entity->isPersisted()) {
			$this->mapper->remove($entity);
			$this->identityMap->remove($entity->getPersistedId());
		}

		$this->identityMap->detach($entity);
		$this->fireEvent($entity, 'onAfterRemove');
		unset($this->isProcessing[spl_object_hash($entity)]);
		return $entity;
	}


	public function flush()
	{
		$this->getModel()->flush();
	}


	public function persistAndFlush(IEntity $entity, $recursive = TRUE)
	{
		$this->persist($entity, $recursive);
		$this->flush();
		return $entity;
	}


	public function removeAndFlush($entity, $recursive = FALSE)
	{
		$this->remove($entity, $recursive);
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

			if (!($result instanceof ICollection || $result instanceof IEntity || $result === NULL)) {
				$result = $this->mapper->toCollection($result);
			}

			return $result;

		} else {
			return parent::__call($method, $args);
		}
	}


	protected function fireEvent(IEntity $entity, $event)
	{
		if (!property_exists($this, $event)) {
			throw new InvalidArgumentException("Event '{$event}' is not defined.");
		}

		$entity->fireEvent($event);
		ObjectMixin::call($this, $event, [$entity]);
	}

}

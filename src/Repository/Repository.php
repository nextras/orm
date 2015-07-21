<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Inflect\Inflect;
use Nette\Object;
use Nette\Utils\ObjectMixin;
use Nextras\Orm\Collection\Helpers\FindByParserHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\IRelationshipCollection;


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

	/** @var array of callbacks with (IEntity[] $persisted, IEntity $removed) arguments */
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

	/** @var array */
	private $isProcessing = [];

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
	public function __construct(IMapper $mapper, IDependencyProvider $dependencyProvider = NULL)
	{
		$this->mapper = $mapper;
		$this->mapper->setRepository($this);
		$this->identityMap = new IdentityMap($this, $dependencyProvider);
		$this->dependencyProvider = $dependencyProvider;

		$annotations = $this->reflection->getAnnotations();
		if (isset($annotations['method'])) {
			foreach ((array) $annotations['method'] as $annotation) {
				$this->proxyMethods[strtolower(preg_replace('#^[^\s]+\s+(\w+)\(.*\).*$#', '$1', $annotation))] = TRUE;
			}
		}
	}


	/** @inheritdoc */
	public function getModel($need = TRUE)
	{
		if ($this->model === NULL && $need) {
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
	public static function getEntityClassNames()
	{
		$class = str_replace('Repository', '', get_called_class());
		return [Inflect::singularize($class)];
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
	public function persist(IEntity $entity, $recursive = TRUE, & $queue = NULL)
	{
		$this->identityMap->check($entity);
		$entityHash = spl_object_hash($entity);
		if (isset($queue[$entityHash]) && $queue[$entityHash] === TRUE) {
			return $entity;
		}

		$isRunner = $queue === NULL;
		if ($isRunner) {
			$queue = [];
		}
		$queue[$entityHash] = TRUE;

		try {

			$this->attach($entity);
			$isPersisted = $entity->isPersisted();
			$this->fireEvent($entity, 'onBeforePersist');
			$this->fireEvent($entity, $isPersisted ? 'onBeforeUpdate' : 'onBeforeInsert');
			$isModified = $entity->isModified();

			if ($recursive) {
				list($prePersist, $postPersist) = PersistanceHelper::getLoadedRelationships($entity);
				foreach ($prePersist as $value) {
					$this->model->getRepositoryForEntity($value)->persist($value, $recursive, $queue);
				}
			}

			if ($isModified) {
				if ($isPersisted) {
					// id can change (composite key)
					$this->identityMap->remove($entity->getPersistedId());
				}

				$id = $this->mapper->persist($entity);
				$entity->fireEvent('onPersist', [$id]);
				$this->identityMap->add($entity);
				$this->entitiesToFlush[0][] = $entity;
			}

			if ($recursive) {
				foreach ($postPersist as $postPersistValue) {
					$hash = spl_object_hash($postPersistValue);
					if (!isset($queue[$hash])) {
						$queue[$hash] = $postPersistValue;
					}
				}

				if ($isRunner) {
					reset($queue);
					while ($value = current($queue)) {
						$hash = key($queue);
						next($queue);
						if ($value === TRUE) {
							continue;
						}

						if ($value instanceof IEntity) {
							$this->model->getRepositoryForEntity($value)->persist($value, $recursive, $queue);
						} elseif ($value instanceof IRelationshipCollection) {
							$value->persist($recursive, $queue);
						}
						$queue[$hash] = TRUE;
					}
				}
			}

			if ($isModified) {
				$this->fireEvent($entity, $isPersisted ? 'onAfterUpdate' : 'onAfterInsert');
				$this->fireEvent($entity, 'onAfterPersist');
			}

		} catch (\Exception $e) {} // finally workaround

		if ($isRunner) {
			$queue = NULL;
		}

		if (isset($e)) {
			throw $e;
		}

		return $entity;
	}


	/** @inheritdoc */
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
			if ($property->relationship !== NULL) {
				if (in_array($property->relationship->type, [
					PropertyRelationshipMetadata::MANY_HAS_ONE,
					PropertyRelationshipMetadata::ONE_HAS_ONE,
					PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED,
				])) {
					$entity->getProperty($property->name)->set(NULL, TRUE);

				} elseif ($property->relationship->type === PropertyRelationshipMetadata::MANY_HAS_MANY) {
					$entity->getValue($property->name)->set([]);

				} else {
					$reverseRepository = $this->model->getRepository($property->relationship->repository);
					$reverseProperty = $reverseRepository->getEntityMetadata()->getProperty($property->relationship->property);

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
			$this->entitiesToFlush[1][] = $entity;
		}

		$this->detach($entity);
		$this->fireEvent($entity, 'onAfterRemove');
		unset($this->isProcessing[spl_object_hash($entity)]);
		return $entity;
	}


	/** @inheritdoc */
	public function flush()
	{
		$this->getModel()->flush();
	}


	/** @inheritdoc */
	public function persistAndFlush(IEntity $entity, $recursive = TRUE)
	{
		$this->persist($entity, $recursive);
		$this->flush();
		return $entity;
	}


	/** @inheritdoc */
	public function removeAndFlush($entity, $recursive = FALSE)
	{
		$this->remove($entity, $recursive);
		$this->flush();
		return $entity;
	}


	/** @inheritdoc */
	public function processFlush()
	{
		$this->mapper->flush();
		$this->onFlush($this->entitiesToFlush[0], $this->entitiesToFlush[1]);
		$entities = $this->entitiesToFlush;
		$this->entitiesToFlush = [[], []];
		return $entities;
	}


	/** @inheritdoc */
	public function processClearIdentityMapAndCaches($areYouSure = NULL)
	{
		if ($areYouSure !== IModel::I_KNOW_WHAT_I_AM_DOING) {
			throw new LogicException('Do not call this method directly. Use IModel::clearIdentityMapAndCaches().');
		}

		$this->identityMap->destroyAllEntities();
		$this->mapper->clearCollectionCache();
	}


	public function __call($method, $args)
	{
		if (isset($this->proxyMethods[strtolower($method)])) {
			if (FindByParserHelper::parse($method, $args)) {
				return call_user_func([$this, $method], $args);
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

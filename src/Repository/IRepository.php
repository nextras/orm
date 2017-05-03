<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;


interface IRepository
{
	/**
	 * @return IModel|null
	 */
	public function getModel();


	public function setModel(IModel $model);


	public function getMapper(): IMapper;


	/**
	 * Hydrates entity.
	 */
	public function hydrateEntity(array $data): IEntity;


	/**
	 * Attaches entity to repository.
	 */
	public function attach(IEntity $entity);


	/**
	 * Detaches entity from repository.
	 */
	public function detach(IEntity $entity);


	/**
	 * Returns possible entity class names for current repository.
	 * @return string[]
	 */
	public static function getEntityClassNames(): array;


	/**
	 * @param string|null    $entityClass for STI (must extends base class)
	 * Returns entity metadata.
	 */
	public function getEntityMetadata(string $entityClass = NULL): EntityMetadata;


	/**
	 * Returns entity class name.
	 */
	public function getEntityClassName(array $data): string;


	/**
	 * Returns IEntity filtered by conditions
	 * @return IEntity|null
	 */
	public function getBy(array $conds);


	/**
	 * Returns entity by primary value.
	 * @param  mixed    $primaryValue
	 * @return IEntity|null
	 */
	public function getById($primaryValue);


	/**
	 * Returns entity collection with all entities.
	 */
	public function findAll(): ICollection;


	/**
	 * Returns entity collection filtered by conditions.
	 */
	public function findBy(array $where): ICollection;


	/**
	 * Returns entities by primary values.
	 * @param  mixed[]  $primaryValues
	 */
	public function findById($primaryValues): ICollection;


	/**
	 * @return mixed
	 */
	public function persist(IEntity $entity, bool $withCascade = true);


	/**
	 * @return mixed
	 */
	public function persistAndFlush(IEntity $entity, bool $withCascade = true);


	/**
	 * @param  IEntity|mixed    $entity
	 */
	public function remove($entity, bool $withCascade = true): IEntity;


	/**
	 * @param  IEntity|mixed    $entity
	 */
	public function removeAndFlush($entity, bool $withCascade = true): IEntity;


	/**
	 * Flushes all persisted changes in all repositories.
	 */
	public function flush();


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doPersist(IEntity $entity);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doRemove(IEntity $entity);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 * The first key contains all flushed persisted entities.
	 * The second key contains all flushed removed entities.
	 * @return [IEntity[], IEntity[]]
	 */
	public function doFlush();


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doClearIdentityMap($areYouSure);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * Fires the event on the entity.
	 * @internal
	 * @ignore
	 * @return void
	 */
	public function doFireEvent(IEntity $entity, string $event);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * Fires the event on the entity.
	 * @internal
	 * @ignore
	 * @return void
	 */
	public function doRefreshAll(bool $allowOverwrite);
}

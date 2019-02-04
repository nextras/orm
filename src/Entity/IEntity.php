<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Repository\IRepository;


interface IEntity
{
	public function getRepository(): IRepository;


	/**
	 * Sets property value.
	 * @param  mixed $value
	 * @return self
	 */
	public function setValue(string $name, $value);


	/**
	 * Sets read-only value.
	 * @param  mixed $value
	 * @return self
	 */
	public function setReadOnlyValue(string $name, $value);


	/**
	 * Returns value.
	 * @return mixed
	 */
	public function &getValue(string $name);


	/**
	 * Returns true if property has a value (not null).
	 */
	public function hasValue(string $name): bool;


	/**
	 * Sets raw value.
	 * @param  mixed    $value
	 */
	public function setRawValue(string $name, $value);


	/**
	 * Returns raw value.
	 * Raw value is normalized value which is suitable unique identification and storing.
	 * @return mixed
	 */
	public function &getRawValue(string $name);


	/**
	 * Returns property contents.
	 * @return mixed|IPropertyContainer
	 */
	public function getProperty(string $name);


	/**
	 * Returns property raw contents.
	 * @return mixed
	 */
	public function getRawProperty(string $name);


	/**
	 * Returns entity metadata.
	 */
	public function getMetadata(): EntityMetadata;


	/**
	 * Returns true if the entity is modiefied or the column $name is modified.
	 */
	public function isModified(string $name = null): bool;


	/**
	 * Sets the entity or the column as modified.
	 * @retun self
	 */
	public function setAsModified(string $name = null);


	/**
	 * Returns true if entity is persisted.
	 */
	public function isPersisted(): bool;


	/**
	 * Returns persisted primary value.
	 * @return mixed
	 */
	public function getPersistedId();


	/**
	 * Returns true if entity is attached to its repository.
	 */
	public function isAttached(): bool;


	// === events ======================================================================================================


	/** @internal */
	public function onCreate();


	/** @internal */
	public function onLoad(array $data);


	/** @internal */
	public function onRefresh(?array $data, bool $isPartial = false);


	/** @internal */
	public function onFree();


	/** @internal */
	public function onAttach(IRepository $repository, EntityMetadata $metadata);


	/** @internal */
	public function onDetach();


	/** @internal */
	public function onPersist($id);


	/** @internal */
	public function onBeforePersist();


	/** @internal */
	public function onAfterPersist();


	/** @internal */
	public function onBeforeInsert();


	/** @internal */
	public function onAfterInsert();


	/** @internal */
	public function onBeforeUpdate();


	/** @internal */
	public function onAfterUpdate();


	/** @internal */
	public function onBeforeRemove();


	/** @internal */
	public function onAfterRemove();
}

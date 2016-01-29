<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Collection\IEntityPreloadContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use Serializable;


interface IEntity extends Serializable
{
	/**
	 * @const
	 * IRelationshipContainer property is returned as IEntity entity.
	 * IRelationshipCollection property is returned as array of its IEntity entities.
	 * Other properties are not changed.
	 */
	const TO_ARRAY_RELATIONSHIP_AS_IS = 1;

	/**
	 * @const
	 * IRelationshipContainer property is returned as entity id.
	 * IRelationshipCollection property is returned as array of entity ids.
	 * Other properties are not changed.
	 */
	const TO_ARRAY_RELATIONSHIP_AS_ID = 2;

	/**
	 * @const
	 * IRelationshipContainer property is returned as array (entity tranformed to array).
	 * IRelationshipCollection property is returned as array of array (entities tranformed to array).
	 * Other properties are not changed.
	 */
	const TO_ARRAY_RELATIONSHIP_AS_ARRAY = 3;


	/**
	 * @const
	 * Skips setting return value form setter.
	 */
	const SKIP_SET_VALUE = "\0";


	/**
	 * Returns entity model.
	 * @param  bool $need
	 * @return IModel
	 */
	public function getModel($need = true);


	/**
	 * Returns entity repository.
	 * @param  bool $need
	 * @return IRepository|null
	 */
	public function getRepository($need = true);


	/**
	 * Fires event.
	 * @param  string   $method
	 * @param  array    $args
	 */
	public function fireEvent($method, $args = []);


	/**
	 * Sets property value.
	 * @param  string   $name
	 * @param  mixed    $value
	 * @return self
	 */
	public function setValue($name, $value);


	/**
	 * Sets read-only value.
	 * @param  string   $name
	 * @param  mixed    $value
	 * @return self
	 */
	public function setReadOnlyValue($name, $value);


	/**
	 * Returns value.
	 * @param  string   $name
	 * @return mixed
	 */
	public function &getValue($name);


	/**
	 * Returns true if property has a value (not null).
	 * @param  string   $name
	 * @return bool
	 */
	public function hasValue($name);


	/**
	 * Sets raw value.
	 * @param  string   $name
	 * @param  mixed    $value
	 */
	public function setRawValue($name, $value);


	/**
	 * Returns raw value.
	 * Raw value is normalized value which is suitable unique identification and storing.
	 * @param  string   $name
	 * @return mixed
	 */
	public function &getRawValue($name);


	/**
	 * Returns property contents.
	 * @param  string   $name
	 * @return mixed|IPropertyContainer
	 */
	public function getProperty($name);


	/**
	 * Returns property raw contents.
	 * @param  string  $name
	 * @return mixed
	 */
	public function getRawProperty($name);


	/**
	 * Converts entity to array.
	 * @param  int  $mode
	 * @return array
	 */
	public function toArray($mode = self::TO_ARRAY_RELATIONSHIP_AS_IS);


	/**
	 * Returns entity metadata.
	 * @return EntityMetadata
	 */
	public function getMetadata();


	/**
	 * Returns true if the entity is modiefied or the column $name is modified.
	 * @param  string   $name
	 * @return bool
	 */
	public function isModified($name = null);


	/**
	 * Sets the entity or the column as modified.
	 * @param  string   $name
	 * @return self
	 */
	public function setAsModified($name = null);


	/**
	 * Returns true if entity is persisted.
	 * @return bool
	 */
	public function isPersisted();


	/**
	 * Returns persisted primary value.
	 * @return mixed
	 */
	public function getPersistedId();


	/**
	 * Returns true if entity is attached to its repository.
	 * @return bool
	 */
	public function isAttached();


	/**
	 * Sets the collection of entites for the loading relations at once.
	 * @param  IEntityPreloadContainer|null     $overIterator
	 */
	public function setPreloadContainer(IEntityPreloadContainer $overIterator = null);


	/**
	 * @return IEntityPreloadContainer
	 */
	public function getPreloadContainer();
}

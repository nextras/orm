<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Collection\IEntityPreloadContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use Serializable;


interface IEntity extends Serializable
{
	/** @const Does not transform relationship entities. */
	const TO_ARRAY_RELATIONSHIP_AS_IS = 1;

	/** @const Transform relationship entities to their ids. */
	const TO_ARRAY_RELATIONSHIP_AS_ID = 2;

	/** @const Transform relationship entities as array. */
	const TO_ARRAY_RELATIONSHIP_AS_ARRAY = 3;

	/** @const Do loaded relatinship */
	const TO_ARRAY_LOADED_RELATIONSHIP_AS_IS = 4;


	/**
	 * Returns entity model.
	 * @param  bool $need
	 * @return IModel
	 */
	public function getModel($need = TRUE);


	/**
	 * Returns entity repository.
	 * @param  bool $need
	 * @return IRepository|NULL
	 */
	public function getRepository($need = TRUE);


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
	 * @param  bool     $allowNull
	 * @param  ICollection
	 * @return mixed
	 */
	public function getValue($name, $allowNull = FALSE);


	/**
	 * Returns TRUE if property has a value (not NULL).
	 * @param  string   $name
	 * @return bool
	 */
	public function hasValue($name);


	/**
	 * Returns raw value
	 * - from IPropertyContainer without transforming
	 * - from entity without validation.
	 *
	 * @param  string   $name
	 * @return mixed
	 */
	public function & getRawValue($name);


	/**
	 * Returns property contents.
	 * @param  string   $name
	 * @return mixed|IPropertyContainer|IPropertyInjection
	 */
	public function getProperty($name);


	/**
	 * Returns foreign key.
	 * Possile to call only for has one relationships.
	 * @param  string   $name
	 * @return mixed
	 */
	public function getForeignKey($name);


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
	public function isModified($name = NULL);


	/**
	 * Sets the entity or the column as modified.
	 * @param  string   $name
	 * @return self
	 */
	public function setAsModified($name = NULL);


	/**
	 * Returns true if entity is persisted.
	 * @return bool
	 */
	public function isPersisted();


	/**
	 * Sets the collection of entites for the loading relations at once.
	 * @param  IEntityPreloadContainer|NULL     $overIterator
	 */
	public function setPreloadContainer(IEntityPreloadContainer $overIterator = NULL);


	/**
	 * @return IEntityPreloadContainer
	 */
	public function getPreloadContainer();

}

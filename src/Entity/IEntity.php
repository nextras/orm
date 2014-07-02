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


interface IEntity
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
	 * @param  bool
	 * @return IModel
	 */
	function getModel($need = TRUE);


	/**
	 * Returns entity repository.
	 * @param  bool
	 * @return IRepository|NULL
	 */
	function getRepository($need = TRUE);


	/**
	 * Fires event.
	 * @param  string
	 * @param  array
	 */
	function fireEvent($method, $args = []);


	/**
	 * Sets property value.
	 * @param  string
	 * @param  mixed
	 * @return self
	 */
	function setValue($name, $value);


	/**
	 * Sets read-only value.
	 * @param  string
	 * @param  mixed
	 * @return self
	 */
	function setReadOnlyValue($name, $value);


	/**
	 * Returns value.
	 * @param  string
	 * @param  bool
	 * @param  ICollection
	 * @return mixed
	 */
	function getValue($name, $allowNull = FALSE);


	/**
	 * Returns TRUE if property has a value (not NULL).
	 * @param  string
	 * @return bool
	 */
	function hasValue($name);


	/**
	 * Returns property contents.
	 * @param  string
	 * @return mixed|IPropertyContainer|IPropertyInjection
	 */
	function getProperty($name);


	/**
	 * Returns foreign key.
	 * Possile to call only for has one relationships.
	 * @param  string
	 * @return mixed
	 */
	function getForeignKey($name);


	/**
	 * Converts entity to array.
	 * @param  int
	 * @return array
	 */
	function toArray($mode = self::TO_ARRAY_RELATIONSHIP_AS_IS);


	/**
	 * Returns entity metadata.
	 * @return EntityMetadata
	 */
	function getMetadata();


	/**
	 * Returns true if the entity is modiefied or the column $name is modified.
	 * @param  string
	 * @return bool
	 */
	function isModified($name = NULL);


	/**
	 * Sets the entity or the column as modified.
	 * @param  string
	 * @return self
	 */
	function setAsModified($name = NULL);


	/**
	 * Sets the collection of entites for the loading relations at once.
	 * @param  IEntityPreloadContainer
	 */
	function setPreloadContainer(IEntityPreloadContainer $overIterator);


	/**
	 * @return IEntityPreloadContainer
	 */
	function getPreloadContainer();

}

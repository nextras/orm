<?php declare(strict_types=1);

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
	 * @return IModel|null
	 */
	public function getModel(bool $need = true);


	/**
	 * Returns entity repository.
	 * @return IRepository|null
	 */
	public function getRepository(bool $need = true);


	/**
	 * Fires event.
	 */
	public function fireEvent(string $method, array $args = []);


	/**
	 * Sets property value.
	 * @param  mixed $value
	 * @return self
	 */
	public function setValue(string $name, $value);


	/**
	 * Sets read-only value.
	 * @param  mixed $value
	 * @return self;
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
	 * Converts entity to array.
	 */
	public function toArray(int $mode = self::TO_ARRAY_RELATIONSHIP_AS_IS): array;


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


	/**
	 * Sets the collection of entites for the loading relations at once.
	 * @param  IEntityPreloadContainer|null     $overIterator
	 */
	public function setPreloadContainer(IEntityPreloadContainer $overIterator = null);


	/**
	 * @return IEntityPreloadContainer|null
	 */
	public function getPreloadContainer();
}

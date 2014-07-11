<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\StorageReflection;

use Traversable;


interface IStorageReflection
{

	/**
	 * Returns default storage name.
	 * @return string
	 */
	public function getDefaultStorageName();


	/**
	 * Returns storage name.
	 * @return string
	 */
	public function getStorageName();


	/**
	 * Sets storage name.
	 * @param  string   $storageName
	 */
	public function setStorageName($storageName);


	/**
	 * Returns entity primary key name.
	 * @return array
	 */
	public function getEntityPrimaryKey();


	/**
	 * Returns storage primary key name.
	 * @return array
	 */
	public function getStoragePrimaryKey();


	/**
	 * Converts entity data to storage key format.
	 *
	 * @param  array|Traversable   $in
	 * @return array
	 */
	public function convertEntityToStorage($in);


	/**
	 * Converts entity key name to storage key format.
	 * @param  string   $key
	 * @return string
	 */
	public function convertEntityToStorageKey($key);


	/**
	 * Converts storage data to entity key format.
	 *
	 * @param  array|Traversable   $in
	 * @return array
	 */
	public function convertStorageToEntity($in);


	/**
	 * Converts storage key name to entity key format.
	 * @param  string   $key
	 * @return string
	 */
	public function convertStorageToEntityKey($key);

}

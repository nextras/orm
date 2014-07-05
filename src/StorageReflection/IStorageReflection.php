<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\StorageReflection;


interface IStorageReflection
{

	/**
	 * Returns default storage name.
	 * @return string
	 */
	function getDefaultStorageName();


	/**
	 * Returns storage name.
	 * @return string
	 */
	function getStorageName();


	/**
	 * Sets storage name.
	 * @param  string $storageName
	 */
	function setStorageName($storageName);


	/**
	 * Returns entity primary key name.
	 * @return array
	 */
	function getEntityPrimaryKey();


	/**
	 * Returns storage primary key name.
	 * @return array
	 */
	function getStoragePrimaryKey();


	/**
	 * Converts entity data to storage key format.
	 * @param  array|\Traversable
	 * @return array
	 */
	function convertEntityToStorage($in);


	/**
	 * Converts entity key name to storage key format.
	 * @param  string
	 * @return string
	 */
	function convertEntityToStorageKey($key);


	/**
	 * Converts storage data to entity key format.
	 * @param  array|\Traversable
	 * @return array
	 */
	function convertStorageToEntity($in);


	/**
	 * Converts storage key name to entity key format.
	 * @param  string
	 * @return string
	 */
	function convertStorageToEntityKey($key);

}

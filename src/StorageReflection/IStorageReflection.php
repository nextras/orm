<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\StorageReflection;

use Traversable;


interface IStorageReflection
{

	/**
	 * Returns storage name.
	 * @return string
	 */
	public function getStorageName();


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

<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\File;

use Nextras\Orm\Mapper\Memory\ArrayMapper;


abstract class FileMapper extends ArrayMapper
{

	protected function saveData(array $data)
	{
		file_put_contents('safe://' . $this->getFileName(), serialize($data));
	}


	protected function readData()
	{
		$fileName = $this->getFileName();
		if (!file_exists($fileName)) {
			return [];
		}

		return unserialize(file_get_contents('safe://' . $fileName));
	}


	/**
	 * Returns file path with file name, where should be stored the mapper contents.
	 * @return string
	 */
	abstract protected function getFileName();

}

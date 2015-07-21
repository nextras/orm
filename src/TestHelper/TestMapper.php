<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\TestHelper;

use Nette\Utils\Callback;
use Nextras\Orm\Mapper\Memory\ArrayMapper;


class TestMapper extends ArrayMapper
{
	/** @var array */
	protected $storage = [];

	/** @var mixed[] array of callbacks */
	protected $methods = [];


	public function addMethod($name, $callback)
	{
		$this->methods[strtolower($name)] = $callback;
	}


	public function __call($name, $args)
	{
		if (isset($this->methods[strtolower($name)])) {
			return Callback::invokeArgs($this->methods[strtolower($name)], $args);
		} else {
			return parent::__call($name, $args);
		}
	}


	protected function readData()
	{
		return $this->storage;
	}


	protected function saveData(array $data)
	{
		$this->storage = $data;
	}

}

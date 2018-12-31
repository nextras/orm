<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\TestHelper;

use Nextras\Orm\Mapper\Memory\ArrayMapper;


class TestMapper extends ArrayMapper
{
	/** @var string */
	protected $storage = '';

	/** @var mixed[] array of callbacks */
	protected $methods = [];


	public function addMethod(string $name, callable $callback)
	{
		$this->methods[strtolower($name)] = $callback;
	}


	public function __call(string $name, array $args)
	{
		if (isset($this->methods[strtolower($name)])) {
			return call_user_func_array($this->methods[strtolower($name)], $args);
		} else {
			return parent::__call($name, $args);
		}
	}


	protected function readData(): array
	{
		return unserialize($this->storage) ?: [];
	}


	protected function saveData(array $data)
	{
		$this->storage = serialize($data);
	}
}

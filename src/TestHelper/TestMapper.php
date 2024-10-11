<?php declare(strict_types = 1);

namespace Nextras\Orm\TestHelper;


use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Mapper\Memory\ArrayMapper;


/**
 * @template E of \Nextras\Orm\Entity\IEntity
 * @extends ArrayMapper<E>
 */
class TestMapper extends ArrayMapper
{
	/** @var string */
	protected string $storage = '';

	/** @var mixed[] array of callbacks */
	protected array $methods = [];


	public function addMethod(string $name, callable $callback): void
	{
		$this->methods[strtolower($name)] = $callback;
	}


	/**
	 * @param mixed[] $args
	 * @return mixed
	 */
	public function __call(string $name, array $args)
	{
		if (isset($this->methods[strtolower($name)])) {
			return call_user_func_array($this->methods[strtolower($name)], $args);
		} else {
			$class = get_class($this);
			throw new MemberAccessException("Call to undefined  method {$class}::{$name}()");
		}
	}


	protected function readData(): array
	{
		$data = unserialize($this->storage);
		return $data !== false ? $data : [];
	}


	protected function saveData(array $data): void
	{
		$this->storage = serialize($data);
	}
}

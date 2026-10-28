<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nette\SmartObject;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\PropertyComparator;
use Nextras\Orm\Exception\InvalidStateException;
use function is_subclass_of;


class PropertyMetadata
{
	use SmartObject;

	public string $name = '';
	public string $containerClassname = '';
	public string|null $wrapper = null;

	public string|null $hasGetter = null;
	public string|null $hasSetter = null;

	/** @var non-empty-array<string, bool> of allowed types defined as keys */
	public array $types;

	public bool $isPrimary = false;
	public bool $isNullable = false;
	public bool $isReadonly = false;
	public bool $isVirtual = false;
	public mixed $defaultValue = null;
	public PropertyRelationshipMetadata|null $relationship = null;

	/** @var array<string, mixed>|null */
	public array|null $args = null;

	/** @var array<mixed>|null array of allowed values */
	public array|null $enum = null;

	private IProperty|null $wrapperPrototype = null;


	public function __construct()
	{
	}


	public function getWrapperPrototype(): IProperty
	{
		if ($this->wrapperPrototype === null) {
			$class = $this->wrapper;
			if ($class === null || !is_subclass_of($class, IProperty::class)) {
				throw new InvalidStateException('Wrapper class has to implement ' . IProperty::class . ' interface.');
			}
			$this->wrapperPrototype = new $class($this);
		}
		return $this->wrapperPrototype;
	}


	public function getPropertyComparator(): PropertyComparator|null
	{
		if ($this->wrapper === null) return null;
		$wrapper = $this->getWrapperPrototype();
		if (!$wrapper instanceof PropertyComparator) return null;
		return $wrapper;
	}


	public function __sleep()
	{
		// we skip wrapperPrototype which may not be serializable and is created lazily
		return [
			'name',
			'containerClassname',
			'wrapper',
			'hasGetter',
			'hasSetter',
			'types',
			'isPrimary',
			'isNullable',
			'isReadonly',
			'isVirtual',
			'defaultValue',
			'relationship',
			'args',
			'enum',
		];
	}


	/**
	 * @param mixed $value
	 */
	public function isValid(&$value): bool
	{
		if ($value === null && $this->isNullable) {
			return true;
		}

		if ($this->enum !== null) {
			return in_array($value, $this->enum, true);
		}

		foreach ($this->types as $rawType => $_) {
			$type = strtolower($rawType);
			if ($type === 'string') {
				if (is_string($value)) {
					return true;
				}
				if (is_int($value) || (is_object($value) && method_exists($value, '__toString'))) {
					$value = (string) $value;
					return true;
				}
			} elseif ($type === 'float') {
				if (is_float($value)) {
					return true;
				}
				if (is_numeric($value)) {
					$value = (float) $value;
					return true;
				} elseif (is_string($value)) {
					$value = (float) str_replace([' ', ','], ['', '.'], $value);
					return true;
				}
			} elseif ($type === 'int') {
				if (is_int($value)) {
					return true;
				}
				if (is_numeric($value)) {
					$value = (int) $value;
					return true;
				} elseif (is_string($value)) {
					$value = (int) str_replace([' ', ','], ['', '.'], $value);
					return true;
				}
			} elseif ($type === 'bool') {
				if (is_bool($value)) {
					return true;
				}
				if (in_array($value, [0, 0.0, '0', 1, 1.0, '1'], true)) {
					$value = (bool) $value;
					return true;
				}
			} elseif ($type === 'array') {
				if (is_array($value)) {
					return true;
				}
			} elseif ($type === 'object') {
				if (is_object($value)) {
					return true;
				}
			} elseif ($type === 'scalar') {
				if (is_scalar($value)) {
					return true;
				}
			} elseif ($type === 'mixed') {
				return true;
			} else {
				if ($value instanceof $type) {
					return true;
				}
			}
		}

		return false;
	}
}

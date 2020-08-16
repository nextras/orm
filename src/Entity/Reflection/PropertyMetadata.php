<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by YetORM https://github.com/uestla/YetORM/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;


use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nette\SmartObject;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Exception\InvalidStateException;


class PropertyMetadata
{
	use SmartObject;


	/** @var string property name */
	public $name = '';

	/** @var string|null */
	public $wrapper;

	/** @var string|null */
	public $hasGetter;

	/** @var string|null */
	public $hasSetter;

	/** @var array<string, bool> of allowed types defined as keys */
	public $types = [];

	/** @var bool */
	public $isPrimary = false;

	/** @var bool */
	public $isNullable = false;

	/** @var bool */
	public $isReadonly = false;

	/** @var bool */
	public $isVirtual = false;

	/** @var mixed */
	public $defaultValue;

	/** @var PropertyRelationshipMetadata|null */
	public $relationship;

	/** @var array<string, mixed>|null */
	public $args;

	/** @var array<mixed>|null array of allowed values */
	public $enum;

	/** @var IProperty|null */
	private $wrapperPrototype;


	public function getWrapperPrototype(): IProperty
	{
		if ($this->wrapperPrototype === null) {
			if ($this->wrapper === null) {
				throw new InvalidStateException();
			}
			$class = $this->wrapper;
			$this->wrapperPrototype = new $class($this);
		}
		return $this->wrapperPrototype;
	}


	public function __sleep()
	{
		// we skip wrapperPrototype which may not be serializable and is created lazily
		return [
			'name',
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
					settype($value, 'float');
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
					settype($value, 'int');
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

			} elseif ($rawType === DateTimeImmutable::class || is_subclass_of($rawType, DateTimeImmutable::class)) {
				if ($value instanceof $rawType) {
					return true;

				} elseif ($value instanceof DateTimeInterface) {
					$value = new $rawType($value->format('c'));
					return true;

				} elseif (is_string($value) && $value !== '') {
					$tmp = new $rawType($value);
					$value = $tmp->setTimezone(new DateTimeZone(date_default_timezone_get()));
					return true;

				} elseif (ctype_digit($value)) {
					$value = new $rawType("@{$value}");
					return true;
				}

			} else {
				if ($value instanceof $type) {
					return true;
				}
			}
		}

		return false;
	}
}

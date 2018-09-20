<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by YetORM https://github.com/uestla/YetORM/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use DateTimeZone;
use Nette\SmartObject;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\InvalidStateException;
use stdClass;


class PropertyMetadata
{
	use SmartObject;


	/** @var string property name */
	public $name = '';

	/** @var string|null */
	public $container;

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

	/** @var stdClass|null */
	public $args;

	/** @var mixed[]|null array of alowed values */
	public $enum;

	/** @var IProperty|null */
	private $containerPrototype;


	public function getPropertyPrototype(): IProperty
	{
		if ($this->containerPrototype === null) {
			if ($this->container === null) {
				throw new InvalidStateException();
			}
			$class = $this->container;
			$this->containerPrototype = new $class($this);
		}
		return $this->containerPrototype;
	}


	public function __sleep()
	{
		return [
			'name',
			'container',
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


	public function isValid(& $value): bool
	{
		if ($value === null && $this->isNullable) {
			return true;
		}

		if ($this->enum) {
			return in_array($value, $this->enum, true);
		}

		foreach ($this->types as $type => $_) {
			if ($type === \DateTimeImmutable::class) {
				if ($value instanceof \DateTimeImmutable) {
					return true;

				} elseif ($value instanceof \DateTime) {
					$value = new \DateTimeImmutable($value->format('c'));
					return true;

				} elseif (is_string($value) && $value !== '') {
					$tmp = new \DateTimeImmutable($value);
					$value = $tmp->setTimezone(new DateTimeZone(date_default_timezone_get()));
					return true;

				} elseif (ctype_digit($value)) {
					$value = new \DateTimeImmutable("@{$value}");
					return true;
				}

			} elseif ($type === \Nextras\Dbal\Utils\DateTimeImmutable::class) {
				if ($value instanceof \Nextras\Dbal\Utils\DateTimeImmutable) {
					return true;

				} elseif ($value instanceof \DateTimeInterface) {
					$value = new \Nextras\Dbal\Utils\DateTimeImmutable($value->format('c'));
					return true;

				} elseif (is_string($value) && $value !== '') {
					$tmp = new \Nextras\Dbal\Utils\DateTimeImmutable($value);
					$value = $tmp->setTimezone(new DateTimeZone(date_default_timezone_get()));
					return true;

				} elseif (ctype_digit($value)) {
					$value = new \Nextras\Dbal\Utils\DateTimeImmutable("@{$value}");
					return true;
				}

			}

			$type = strtolower($type);
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

			} else {
				if ($value instanceof $type) {
					return true;
				}
			}
		}

		return false;
	}
}

<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by YetORM https://github.com/uestla/YetORM/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use DateTimeZone;
use Nette\Object;
use stdClass;


class PropertyMetadata extends Object
{
	/** @var string property name */
	public $name = '';

	/** @var string|NULL */
	public $container;

	/** @var bool */
	public $hasGetter = FALSE;

	/** @var bool */
	public $hasSetter = FALSE;

	/** @var array of allowed types defined as keys */
	public $types = [];

	/** @var bool */
	public $isPrimary = FALSE;

	/** @var bool */
	public $isNullable = FALSE;

	/** @var bool */
	public $isReadonly = FALSE;

	/** @var bool */
	public $isVirtual = FALSE;

	/** @var mixed */
	public $defaultValue;

	/** @var PropertyRelationshipMetadata|NULL */
	public $relationship;

	/** @var stdClass|NULL */
	public $args;

	/** @var mixed[]|NULL array of alowed values */
	public $enum;


	public function isValid(& $value)
	{
		if ($value === NULL && $this->isNullable) {
			return TRUE;
		}

		if ($this->enum) {
			return in_array($value, $this->enum, TRUE);
		}

		foreach ($this->types as $type => $_) {
			$type = strtolower($type);
			if ($type === 'datetime') {
				if ($value instanceof \DateTime) {
					return TRUE;

				} elseif ($value instanceof \DateTimeImmutable) {
					$value = new \DateTime($value->format('c'));
					return TRUE;

				} elseif (is_string($value) && $value !== '') {
					$value = new \DateTime($value);
					$value->setTimezone(new DateTimeZone(date_default_timezone_get()));
					return TRUE;

				} elseif (ctype_digit($value)) {
					$value = new \DateTime("@{$value}");
					return TRUE;
				}

			} elseif ($type === 'datetimeimmutable') {
				if ($value instanceof \DateTimeImmutable) {
					return TRUE;

				} elseif ($value instanceof \DateTime) {
					$value = new \DateTimeImmutable($value->format('c'));
					return TRUE;

				} elseif (is_string($value) && $value !== '') {
					$tmp = new \DateTimeImmutable($value);
					$value = $tmp->setTimezone(new DateTimeZone(date_default_timezone_get()));
					return TRUE;

				} elseif (ctype_digit($value)) {
					$value = new \DateTimeImmutable("@{$value}");
					return TRUE;
				}

			} elseif ($type === 'string') {
				if (is_string($value)) return TRUE;
				if (is_int($value) || (is_object($value) && method_exists($value, '__toString'))) {
					$value = (string) $value;
					return TRUE;
				}

			} elseif ($type === 'float') {
				if (is_float($value)) return TRUE;
				if (is_numeric($value)) {
					settype($value, 'float');
					return TRUE;
				} elseif (is_string($value)) {
					$value = (float) str_replace(array(' ', ','), array('', '.'), $value);
					return TRUE;
				}

			} elseif ($type === 'int') {
				if (is_int($value)) return TRUE;
				if (is_numeric($value)) {
					settype($value, 'int');
					return TRUE;
				} elseif (is_string($value)) {
					$value = (int) str_replace(array(' ', ','), array('', '.'), $value);
					return TRUE;
				}

			} elseif ($type === 'bool') {
				if (is_bool($value)) return TRUE;
				if (in_array($value, array(0, 0.0, '0', 1, 1.0, '1'), TRUE)) {
					$value = (bool) $value;
					return TRUE;
				}

			} elseif ($type === 'array') {
				if (is_array($value)) return TRUE;

			} elseif ($type === 'object') {
				if (is_object($value)) return TRUE;

			} elseif ($type === 'scalar') {
				if (is_scalar($value)) return TRUE;

			} elseif ($type === 'mixed') {
				return TRUE;

			} else {
				if ($value instanceof $type) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}
}

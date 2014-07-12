<?php

/**
 * This file is part of the Nextras\ORM library.
 * This file was inspired by YetORM https://github.com/uestla/YetORM/.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\Object;
use Nette\Utils\DateTime;
use Nextras\Orm\InvalidArgumentException;
use stdClass;


class PropertyMetadata extends Object
{
	/** @const int Property access types */
	const READ = 1;
	const WRITE = 2;
	const READWRITE = 3;

	/** @const int Relationship types */
	const RELATIONSHIP_ONE_HAS_ONE = 1;
	const RELATIONSHIP_ONE_HAS_ONE_DIRECTED = 2;
	const RELATIONSHIP_ONE_HAS_MANY = 3;
	const RELATIONSHIP_MANY_HAS_ONE = 4;
	const RELATIONSHIP_MANY_HAS_MANY = 5;

	/** @var string property name */
	public $name;

	/** @var string|NULL */
	public $container;

	/** @var bool */
	public $hasGetter = FALSE;

	/** @var bool */
	public $hasSetter = FALSE;

	/** @var array */
	public $types;

	/** @var bool */
	public $isNullable;

	/** @var bool */
	public $isReadonly;

	/** @var int */
	public $access;

	/** @var stdClass */
	public $args;

	/** @var string */
	public $relationshipRepository;

	/** @var string */
	public $relationshipProperty;

	/** @var bool */
	public $relationshipIsMain = FALSE;

	/** @var int */
	public $relationshipType;

	/** @var mixed[] */
	public $enum;


	public function __construct($name, $types, $access = self::READWRITE)
	{
		$this->name = $name;
		$this->args = (object) NULL;
		$this->setTypes($types);
		$this->setAccess($access);
	}


	public function setTypes($types)
	{
		static $alliases = [
			'void' => 'null',
			'double' => 'float',
			'real' => 'float',
			'numeric' => 'float',
			'number' => 'float',
			'integer' => 'int',
			'boolean' => 'bool',
			'text' => 'string',
		];

		if (is_scalar($types)) {
			$types = explode('|', $types);
		}

		$this->types = [];
		foreach ($types as $type) {
			$_type = strtolower(trim($type));
			if (isset($alliases[$_type])) {
				$_type = $alliases[$_type];
			}

			$this->types[$_type] = TRUE;
		}

		$this->isNullable = isset($this->types['null']) || isset($this->types['NULL']);
		unset($this->types['null'], $this->types['NULL']);

		return $this;
	}


	public function setAccess($access)
	{
		if (!in_array($access, [self::READWRITE, self::READ])) {
			throw new InvalidArgumentException('Invalid property access type.');
		}

		$this->access = $access;
		$this->isReadonly = !($this->access & self::WRITE);
		return $this;
	}


	public function isValid(& $value)
	{
		if ($value === NULL && $this->isNullable) {
			return TRUE;
		}

		if ($this->enum) {
			return in_array($value, $this->enum, TRUE);
		}

		foreach ($this->types as $type => $foo) {
			if ($type === 'datetime') {
				if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
					return TRUE;
				}
				if ($value !== '' && (is_string($value) || is_int($value) || is_float($value))) {
					$value = DateTime::from($value);
					return TRUE;
				}

			} elseif ($type === 'string') {
				if (is_string($value)) return TRUE;
				if (is_int($value) || is_float($value) || (is_object($value) && method_exists($value, '__toString'))) {
					$value = (string) $value;
					return TRUE;
				}

			} elseif ($type === 'float') {
				if (is_float($value)) return TRUE;
				if (is_numeric($value)) {
					settype($value, 'float');
					return TRUE;
				} elseif (is_string($value)) {
					$value = (int) str_replace(array(' ', ','), array('', '.'), $value);
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
				if (is_object($value)) {
					settype($value, 'array');
					return TRUE;
				}

			} elseif ($type === 'object') {
				if (is_object($value)) return TRUE;
				if (is_array($value)) {
					settype($value, 'object');
					return TRUE;
				}

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

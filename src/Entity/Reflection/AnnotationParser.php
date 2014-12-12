<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Reflection;

use Inflect\Inflect;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\StorageReflection\IStorageReflection;
use ReflectionClass;


class AnnotationParser
{
	/** @var array */
	public static $modifiers = [
		'1:1' => 'parseOneHasOne',
		'1:1d' => 'parseOneHasOneDirected',
		'1:m' => 'parseOneHasMany',
		'1:n' => 'parseOneHasMany',
		'm:1' => 'parseManyHasOne',
		'n:1' => 'parseManyHasOne',
		'm:m' => 'parseManyHasMany',
		'm:n' => 'parseManyHasMany',
		'n:m' => 'parseManyHasMany',
		'n:n' => 'parseManyHasMany',
		'enum' => 'parseEnum',
		'virtual' => 'parseVirtual',
		'container' => 'parseContainer',
		'default' => 'parseDefault',
		'primary' => 'parsePrimary',
	];

	/** @var ClassType */
	protected $reflection;

	/** @var EntityMetadata */
	protected $metadata;

	/** @var array */
	protected $storageProperties = [];

	/** @var array */
	protected $primaryKey = [];


	public function parseMetadata($class, & $fileDependencies)
	{
		$this->reflection = new ClassType($class);
		$this->metadata = new EntityMetadata($class);
		$this->storageProperties = [];
		$this->primaryKey = [];

		$this->loadProperties($fileDependencies);
		$this->loadGettersSetters();

		// makes id property virtual on entities with composite primary key
		if ($this->primaryKey && $this->metadata->hasProperty('id')) {
			unset($this->storageProperties['id']);
		}

		$fileDependencies = array_unique($fileDependencies);

		$this->metadata->setPrimaryKey($this->primaryKey ?: ['id']);
		$this->metadata->setStorageProperties(array_keys($this->storageProperties));
		return $this->metadata;
	}


	protected function loadGettersSetters()
	{
		$methods = [];
		foreach ($this->reflection->getMethods() as $method) {
			$methods[strtolower($method->name)] = $method;
		}

		foreach ($this->metadata->getProperties() as $name => $property) {
			$getter = 'get' . strtolower($name);
			if (isset($methods[$getter])) {
				$property->hasGetter = TRUE;
			}
			$setter = 'set' . strtolower($name);
			if (isset($methods[$setter])) {
				$property->hasSetter = TRUE;
			}
		}
	}


	protected function loadProperties(& $fileDependencies)
	{
		$classTree = [$current = $this->reflection->name];
		while (($current = get_parent_class($current)) !== FALSE) {
			if (strpos($current, 'Fragment') !== FALSE) {
				break;
			}

			$classTree[] = $current;
		}

		foreach (array_reverse($classTree) as $class) {
			$reflection = ClassType::from($class);
			$fileDependencies[] = $reflection->getFileName();
			$this->parseAnnotations($reflection);
		}
	}


	protected function parseAnnotations(ClassType $reflection)
	{
		foreach ($reflection->getAnnotations() as $annotation => $values) {
			if ($annotation === 'property') {
				$access = PropertyMetadata::READWRITE;
			} elseif ($annotation === 'property-read') {
				$access = PropertyMetadata::READ;
			} else {
				continue;
			}

			foreach ($values as $value) {
				$splitted = preg_split('#\s+#', $value, 3);
				if (count($splitted) < 2 || $splitted[1][0] !== '$') {
					throw new InvalidArgumentException("Annotation syntax error '$value'.");
				}

				$name = substr($splitted[1], 1);
				$types = $this->parseAnnotationTypes($splitted[0], $reflection);
				if ($access === PropertyMetadata::READWRITE) {
					$this->storageProperties[$name] = TRUE;
				}
				$this->parseAnnotationValue($name, $types, $access, isset($splitted[2]) ? $splitted[2] : NULL);
			}
		}
	}


	protected function parseAnnotationTypes($typesString, ClassType $reflection)
	{
		static $allTypes = [
			'array', 'bool', 'boolean', 'double', 'float', 'int', 'integer', 'mixed',
			'numeric', 'number', 'null', 'object', 'real', 'string', 'text', 'void',
			'datetime', 'scalar'
		];

		$types = [];
		foreach (explode('|', $typesString) as $type) {
			if (strpos($type, '[') !== FALSE) { // Class[]
				$type = 'array';
			} elseif (!in_array(strtolower($type), $allTypes)) {
				$type = AnnotationsParser::expandClassName($type, $reflection);
			}
			$types[] = $type;
		}

		return $types;
	}


	protected function parseAnnotationValue($name, array $types, $access, $params)
	{
		$property = new PropertyMetadata($name, $types, $access);
		$this->processDefaultContainer($property);

		$this->metadata->setProperty($name, $property);
		if ($params) {
			preg_match_all('#\{([^}]+)\}#i', $params, $matches, PREG_SET_ORDER);
			if ($matches) {
				foreach ($matches as $match) {
					$this->processPropertyModifier($property, preg_split('#\s+#', $match[1]));
				}
			}
		}
	}


	protected function processDefaultContainer(PropertyMetadata $property)
	{
		if (isset($property->types['nette\utils\datetime']) || isset($property->types['datetime'])) {
			$property->container = 'Nextras\Orm\Entity\PropertyContainers\DateTimePropertyContainer';
		}
	}


	protected function processPropertyModifier(PropertyMetadata $property, array $matches)
	{
		$type = strtolower($matches[0]);
		if (!isset(static::$modifiers[$type])) {
			throw new InvalidArgumentException("Unknown property modifier '$type'.");
		}

		$callback = static::$modifiers[$type];
		if (!is_array($callback)) {
			$callback = [$this, $callback];
		}
		call_user_func_array($callback, array_merge([$property], [array_slice($matches, 1)]));
	}


	protected function parseOneHasOne(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {1:1} relationship.');
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE;
		$property->relationshipRepository = $this->makeFQN(array_shift($args));
		$property->relationshipProperty = $this->getPropertyNameSingular(array_shift($args));
		$property->container = 'Nextras\Orm\Relationships\OneHasOne';
	}


	protected function parseOneHasOneDirected(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {1:1d} relationship.');
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED;
		$property->relationshipRepository = $this->makeFQN(array_shift($args));
		$property->container = 'Nextras\Orm\Relationships\OneHasOneDirected';

		if (count($args) === 2) {
			$property->relationshipProperty = $this->getPropertyNameSingular(array_shift($args));
			$property->relationshipIsMain = array_shift($args) === 'primary';
		} else {
			$arg = array_shift($args);
			$property->relationshipProperty = $this->getPropertyNameSingular($arg === 'primary' ? NULL : $arg);
			$property->relationshipIsMain = $arg === 'primary';
		}
	}


	protected function parseOneHasMany(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {1:m} relationship.');
		}

		$arg = array_pop($args);
		if (stripos($arg, 'order:') === 0) {
			$property->args->relationship = ['order' => explode(',', substr($arg, 6)) + [1 => ICollection::ASC]];
		} else {
			$args[] = $arg;
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY;
		$property->relationshipRepository = $this->makeFQN(array_shift($args));
		$property->relationshipProperty = $this->getPropertyNameSingular(array_shift($args));
		$property->container = 'Nextras\Orm\Relationships\OneHasMany';
	}


	protected function parseManyHasOne(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:1} relationship.');
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE;
		$property->relationshipRepository = $this->makeFQN(array_shift($args));
		$property->relationshipProperty = $this->getPropertyNamePlural(array_shift($args));
		$property->container = 'Nextras\Orm\Relationships\ManyHasOne';
	}


	protected function parseManyHasMany(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:n} relationship.');
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY;
		$property->relationshipRepository = $this->makeFQN(array_shift($args));
		$property->container = 'Nextras\Orm\Relationships\ManyHasMany';

		$arg = array_pop($args);
		if (stripos($arg, 'order:') === 0) {
			$property->args->relationship = ['order' => explode(',', substr($arg, 6)) + [1 => ICollection::ASC]];
		} else {
			$args[] = $arg;
		}

		if (count($args) === 2) {
			$property->relationshipProperty = $this->getPropertyNamePlural(array_shift($args));
			$property->relationshipIsMain = array_shift($args) === 'primary';
		} else {
			$arg = array_shift($args);
			$property->relationshipProperty = $this->getPropertyNamePlural($arg === 'primary' ? NULL : $arg);
			$property->relationshipIsMain = $arg === 'primary';
		}
	}


	protected function parseEnum(PropertyMetadata $property, array $args)
	{
		$enumValues = [];

		foreach ($args as $arg) {
			list($className, $const) = explode('::', $arg);
			if ($className === 'self' || $className === 'static') {
				$className = $this->metadata->className;
			} else {
				$className = $this->makeFQN($className);
			}

			$classReflection = new ReflectionClass($className);
			$constants = $classReflection->getConstants();

			if (strpos($const, '*') !== FALSE) {
				$prefix = rtrim($const, '*');
				$prefixLength = strlen($prefix);
				$count = 0;
				foreach ($constants as $name => $value) {
					if (substr($name, 0, $prefixLength) === $prefix) {
						$enumValues[$value] = $value;
						$count += 1;
					}
				}
				if ($count === 0) {
					throw new InvalidArgumentException("No constant matching {$classReflection->name}::{$const} pattern required by enum macro in {$this->reflection->name}::\${$property->name} found.");
				}
			} else {
				if (!array_key_exists($const, $constants)) {
					throw new InvalidArgumentException("Constant {$classReflection->name}::{$const} required by enum macro in {$this->reflection->name}::\${$property->name} not found.");
				}
				$value = $classReflection->getConstant($const);
				$enumValues[$value] = $value;
			}
		}

		$property->enum = array_values($enumValues);
	}


	protected function parseVirtual(PropertyMetadata $property, array $args)
	{
		unset($this->storageProperties[$property->name]);
	}


	protected function parseContainer(PropertyMetadata $property, $args)
	{
		$property->container = $this->makeFQN($args[0]);
	}


	protected function parseDefault(PropertyMetadata $property, $args)
	{
		$property->defaultValue = $this->parseLiteral($args[0], $property);
	}


	protected function parsePrimary(PropertyMetadata $propertyMetadata, $args)
	{
		$this->primaryKey[] = $propertyMetadata->name;
	}


	protected function makeFQN($name)
	{
		return AnnotationsParser::expandClassName($name, $this->reflection);
	}


	protected function getPropertyNameSingular($arg)
	{
		return $arg ? ltrim($arg, '$') : lcfirst($this->reflection->getShortName());
	}


	protected function getPropertyNamePlural($arg)
	{
		return $arg ? ltrim($arg, '$') : Inflect::pluralize(lcfirst($this->reflection->getShortName()));
	}


	protected function parseLiteral($literal, PropertyMetadata $property)
	{
		if (strcasecmp($literal, 'true') === 0) {
			return TRUE;
		} elseif (strcasecmp($literal, 'false') === 0) {
			return FALSE;
		} elseif (strcasecmp($literal, 'null') === 0) {
			return NULL;
		} elseif (strpos($literal, '::') !== FALSE) {
			list($className, $const) = explode('::', $literal);
			if ($className === 'self' || $className === 'static') {
				$className = $this->metadata->className;
			} else {
				$className = $this->makeFQN($className);
			}

			$classReflection = new ReflectionClass($className);
			$constants = $classReflection->getConstants();
			if (!array_key_exists($const, $constants)) {
				throw new InvalidArgumentException("Constant {$classReflection->name}::{$const} required by default macro in {$this->reflection->name}::\${$property->name} not found.");
			}
			return $constants[$const];

		} else {
			return $literal;
		}
	}

}

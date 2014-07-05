<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 * @author     Petr Kessler (http://kesspess.1991.cz)
 */

namespace Nextras\Orm\Entity\Reflection;

use Inflect\Inflect;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


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
		'primary' => 'parsePrimary',
		'virtual' => 'parseVirtual',
		'filteredrelationship' => 'parseFilteredRelationship',
		'container' => 'parseContainer',
	];

	/** @var array */
	private static $reflections = [];


	/**
	 * @param  string
	 * @return ClassType
	 */
	private static function getReflection($class)
	{
		if (!isset(self::$reflections[$class])) {
			self::$reflections[$class] = new ClassType($class);
		}

		return self::$reflections[$class];
	}


	/** @var ClassType */
	private $reflection;

	/** @var EntityMetadata */
	private $metadata;


	public function __construct($class)
	{
		$this->reflection = static::getReflection($class);
		$this->metadata = new EntityMetadata;
		$this->metadata->entityClass = $class;
	}


	public function getMetadata(& $fileDependencies)
	{
		$this->loadProperties($fileDependencies);
		$this->loadGettersSetters();

		$fileDependencies = array_unique($fileDependencies);

		$count = count($this->metadata->primaryKey);
		if ($count === 0) {
			$this->metadata->primaryKey = ['id'];
			$this->metadata->getProperty('id')->hasGetter = FALSE;
			$this->metadata->getProperty('id')->hasSetter = FALSE;
		} elseif ($count === 1) {
			throw new InvalidStateException('Composite primary key have to consist of two and more properties.');
		}

		$this->metadata->storageProperties = array_keys($this->metadata->storageProperties);
		return $this->metadata;
	}


	private function loadGettersSetters()
	{
		$methods = array();
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


	private function loadProperties(& $fileDependencies)
	{
		$classTree = array($current = $this->reflection->name);
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


	private function parseAnnotations(ClassType $reflection)
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
					$this->metadata->storageProperties[$name] = TRUE;
				}
				$this->parseAnnotationValue($name, $types, $access, isset($splitted[2]) ? $splitted[2] : NULL);
			}
		}
	}


	private function parseAnnotationTypes($typesString, ClassType $reflection)
	{
		static $allTypes = array(
			'array', 'bool', 'boolean', 'double', 'float', 'int', 'integer', 'mixed',
			'numeric', 'number', 'null', 'object', 'real', 'string', 'text', 'void',
		);

		$types = array();
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


	private function parseAnnotationValue($name, array $types, $access, $params)
	{
		$property = new PropertyMetadata($name, $types, $access);
		$this->processDefaultContainer($property);

		$this->metadata->setProperty($name, $property);
		if ($params) {
			preg_match_all('#\{([^}]+)\}#i', $params, $matches, PREG_SET_ORDER);
			if ($matches) {
				foreach ($matches as $match) {
					$this->processPropertyModifier($property, preg_split('#[,\s]\s*#', $match[1]));
				}
			}
		}
	}


	private function processDefaultContainer(PropertyMetadata $property)
	{
		if (isset($property->types['nette\utils\datetime']) || isset($property->types['datetime'])) {
			$property->container = 'Nextras\Orm\Entity\PropertyContainers\DateTimePropertyContainer';
		}
	}


	private function processPropertyModifier(PropertyMetadata $property, array $matches)
	{
		$type = strtolower($matches[0]);
		if (!isset(static::$modifiers[$type])) {
			throw new InvalidArgumentException("Unknown property modifier '$type'.");
		}

		$callback = static::$modifiers[$type];
		if (!is_array($callback)) {
			$callback = array($this, $callback);
		}
		call_user_func_array($callback, array_merge(array($property), array(array_slice($matches, 1))));
	}


	private function parseOneHasOne(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:n} relationship.');
		}

		$args[0] = $this->makeFQN($args[0]);
		$args[1] = isset($args[1]) ? ltrim($args[1], '$') : lcfirst($this->reflection->getShortName());

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE;
		$property->relationshipRepository = $args[0];
		$property->relationshipProperty = $args[1];

		$property->container = 'Nextras\Orm\Relationships\OneHasOne';
		$property->args = $args;
	}


	private function parseOneHasOneDirected(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:n} relationship.');
		}

		$p    = [];
		$p[0] = $this->makeFQN(array_shift($args));

		$token = array_shift($args);
		if (strcasecmp($token, 'primary') === 0) {
			$p[1] = Inflect::pluralize(lcfirst($this->reflection->getShortName()));
			$p[2] = TRUE;
		} else {
			$p[1] = $token ? ltrim($token, '$') : Inflect::pluralize(lcfirst($this->reflection->getShortName()));
			$p[2] = strcasecmp(array_shift($args), 'primary') === 0;
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE_DIRECTED;
		$property->relationshipRepository = $p[0];
		$property->relationshipProperty = $p[1];
		$property->relationshipIsMain = $p[2];
		$property->args = $p;
		$property->container = 'Nextras\Orm\Relationships\OneHasOneDirected';

		if (!$property->relationshipIsMain) {
			unset($this->metadata->storageProperties[$property->name]);
		}
	}


	private function parseOneHasMany(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:n} relationship.');
		}

		$args[0] = $this->makeFQN($args[0]);
		$args[1] = isset($args[1]) ? ltrim($args[1], '$') : lcfirst($this->reflection->getShortName());

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY;
		$property->relationshipRepository = $args[0];
		$property->relationshipProperty = $args[1];

		$property->args = $args;
		$property->container = 'Nextras\Orm\Relationships\OneHasMany';
	}


	private function parseManyHasOne(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:n} relationship.');
		}

		$args[0] = $this->makeFQN($args[0]);
		$args[1] = isset($args[1]) ? ltrim($args[1], '$') : Inflect::pluralize(lcfirst($this->reflection->getShortName()));

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE;
		$property->relationshipRepository = $args[0];
		$property->relationshipProperty = $args[1];

		$property->args = $args;
		$property->container = 'Nextras\Orm\Relationships\ManyHasOne';
	}


	private function parseManyHasMany(PropertyMetadata $property, array $args)
	{
		if (count($args) === 0) {
			throw new InvalidStateException('Missing repository name for {m:n} relationship.');
		}

		$p    = [];
		$p[0] = $this->makeFQN(array_shift($args));

		$token = array_shift($args);
		if (strcasecmp($token, 'primary') === 0) {
			$p[1] = Inflect::pluralize(lcfirst($this->reflection->getShortName()));
			$p[2] = TRUE;
		} else {
			$p[1] = $token ? ltrim($token, '$') : Inflect::pluralize(lcfirst($this->reflection->getShortName()));
			$p[2] = strcasecmp(array_shift($args), 'primary') === 0;
		}

		$property->relationshipType = PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY;
		$property->relationshipRepository = $p[0];
		$property->relationshipProperty = $p[1];
		$property->relationshipIsMain = $p[2];
		$property->args = $p;

		$property->container = 'Nextras\Orm\Relationships\ManyHasMany';
	}


	private function parseEnum(PropertyMetadata $property, array $args)
	{
		// $property->
	}


	private function parsePrimary(PropertyMetadata $property, array $args)
	{
		$this->metadata->primaryKey[] = $property->name;
	}


	private function parseVirtual(PropertyMetadata $property, array $args)
	{
		unset($this->metadata->storageProperties[$property->name]);
	}


	private function parseFilteredRelationship(PropertyMetadata $property, $args)
	{
		$sourceName = ltrim($args[0], '$');
		$sourceProperty = $this->metadata->getProperty($sourceName);

		if ($sourceProperty->relationshipType === PropertyMetadata::RELATIONSHIP_ONE_HAS_ONE
			|| $sourceProperty->relationshipType === PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE) {
			$property->container = 'Nextras\Orm\Entity\PropertyContainers\FilteredRelationshipContainerContainer';
		} else {
			$property->container = 'Nextras\Orm\Entity\PropertyContainers\FilteredRelationshipCollectionContainer';
		}

		$property->args = [$sourceName];
		unset($this->metadata->storageProperties[$property->name]);
	}


	private function parseContainer(PropertyMetadata $property, $args)
	{
		$property->container = $this->makeFQN($args[0]);
	}


	private function makeFQN($name)
	{
		return AnnotationsParser::expandClassName($name, $this->reflection);
	}

}

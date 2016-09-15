<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidModifierDefinitionException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\ManyHasOne;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Relationships\OneHasOne;


class MetadataParser implements IMetadataParser
{
	/** @var array */
	protected $modifiers = [
		'1:1' => 'parseOneHasOne',
		'1:1d' => 'parseOneHasOne',
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
		'primary-proxy' => 'parsePrimaryProxy',
	];

	/** @var ClassType */
	protected $reflection;

	/** @var ClassType */
	protected $currentReflection;

	/** @var EntityMetadata */
	protected $metadata;

	/** @var array */
	protected $entityClassesMap;

	/** @var ModifierParser */
	protected $modifierParser;


	public function __construct(array $entityClassesMap)
	{
		$this->entityClassesMap = $entityClassesMap;
		$this->modifierParser = new ModifierParser();
	}


	/**
	 * Adds modifier processor.
	 * @param  string $modifier
	 * @param  callable $processor
	 * @return self
	 */
	public function addModifier($modifier, callable $processor)
	{
		$this->modifiers[strtolower($modifier)] = $processor;
		return $this;
	}


	public function parseMetadata($class, & $fileDependencies)
	{
		$this->reflection = new ClassType($class);
		$this->metadata = new EntityMetadata($class);

		$this->loadProperties($fileDependencies);
		$this->loadGettersSetters();
		$this->initPrimaryKey();

		$fileDependencies = array_unique($fileDependencies);
		return $this->metadata;
	}


	protected function loadGettersSetters()
	{
		$methods = [];
		foreach ($this->reflection->getMethods() as $method) {
			$methods[strtolower($method->name)] = $method;
		}

		foreach ($this->metadata->getProperties() as $name => $property) {
			$getter = 'getter' . strtolower($name);
			if (isset($methods[$getter])) {
				$property->hasGetter = $getter;
			}
			$setter = 'setter' . strtolower($name);
			if (isset($methods[$setter])) {
				$property->hasSetter = $setter;
			}
		}
	}


	protected function loadProperties(& $fileDependencies)
	{
		$classTree = [$current = $this->reflection->name];
		while (($current = get_parent_class($current)) !== false) {
			$classTree[] = $current;
		}

		foreach (array_reverse($classTree) as $class) {
			$reflection = ClassType::from($class);
			$fileDependencies[] = $reflection->getFileName();
			$this->currentReflection = $reflection;
			$this->parseAnnotations($reflection);
		}
	}


	protected function parseAnnotations(ClassType $reflection)
	{
		foreach ($reflection->getAnnotations() as $annotation => $values) {
			if ($annotation === 'property') {
				$isReadonly = false;
			} elseif ($annotation === 'property-read') {
				$isReadonly = true;
			} else {
				continue;
			}

			foreach ($values as $value) {
				$splitted = preg_split('#\s+#', $value, 3);
				if (count($splitted) < 2 || $splitted[1][0] !== '$') {
					throw new InvalidArgumentException("Annotation syntax error '$value'.");
				}

				$property = new PropertyMetadata();
				$property->name = substr($splitted[1], 1);
				$property->isReadonly = $isReadonly;
				$this->metadata->setProperty($property->name, $property);

				$this->parseAnnotationTypes($property, $splitted[0]);
				$this->parseAnnotationValue($property, isset($splitted[2]) ? $splitted[2] : null);
			}
		}
	}


	protected function parseAnnotationTypes(PropertyMetadata $property, $typesString)
	{
		static $allTypes = [
			'array', 'bool', 'boolean', 'double', 'float', 'int', 'integer', 'mixed',
			'numeric', 'number', 'null', 'object', 'real', 'string', 'text', 'void',
			'datetime', 'datetimeimmutable', 'scalar',
		];
		static $alliases = [
			'double' => 'float',
			'real' => 'float',
			'numeric' => 'float',
			'number' => 'float',
			'integer' => 'int',
			'boolean' => 'bool',
		];

		$types = [];
		foreach (explode('|', $typesString) as $type) {
			if (strpos($type, '[') !== false) { // Class[]
				$type = 'array';
			} elseif (!in_array(strtolower($type), $allTypes, true)) {
				$type = $this->makeFQN($type);
			} elseif (isset($alliases[strtolower($type)])) {
				$type = $alliases[strtolower($type)];
			}
			$types[$type] = true;
		}

		$property->isNullable = isset($types['null']) || isset($types['NULL']);
		unset($types['null'], $types['NULL']);
		$property->types = $types;
	}


	protected function parseAnnotationValue(PropertyMetadata $property, $propertyComment)
	{
		if (!$propertyComment) {
			return;
		}

		$matches = $this->modifierParser->matchModifiers($propertyComment);
		foreach ($matches as $macroContent) {
			try {
				$args = $this->modifierParser->parse($macroContent, $this->currentReflection);
			} catch (InvalidModifierDefinitionException $e) {
				throw new InvalidModifierDefinitionException(
					"Invalid modifier definition for {$this->currentReflection->name}::\${$property->name} property.",
					0,
					$e
				);
			}
			$this->processPropertyModifier($property, $args[0], $args[1]);
		}
	}


	protected function processPropertyModifier(PropertyMetadata $property, $modifier, array $args)
	{
		$type = strtolower($modifier);
		if (!isset($this->modifiers[$type])) {
			throw new InvalidModifierDefinitionException(
				"Unknown modifier '$type' type for {$this->currentReflection->name}::\${$property->name} property."
			);
		}

		$callback = $this->modifiers[$type];
		if (!is_array($callback)) {
			$callback = [$this, $callback];
		}
		call_user_func_array($callback, [$property, &$args]);
		if (!empty($args)) {
			$parts = [];
			foreach ($args as $key => $val) {
				if (is_numeric($key) && !is_array($val)) {
					$parts[] = $val;
					continue;
				}
				$parts[] = $key;
			}
			throw new InvalidModifierDefinitionException(
				"Modifier {{$type}} in {$this->currentReflection->name}::\${$property->name} property has unknown arguments: " . implode(', ', $parts) . '.'
			);
		}
	}


	protected function parseOneHasOne(PropertyMetadata $property, array &$args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_ONE;
		$property->container = OneHasOne::class;
		$this->processRelationshipIsMain($args, $property);
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipCascade($args, $property);
	}


	protected function parseOneHasMany(PropertyMetadata $property, array &$args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_MANY;
		$property->container = OneHasMany::class;
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipCascade($args, $property);
		$this->processRelationshipOrder($args, $property);
	}


	protected function parseManyHasOne(PropertyMetadata $property, array &$args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::MANY_HAS_ONE;
		$property->container = ManyHasOne::class;
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipCascade($args, $property);
	}


	protected function parseManyHasMany(PropertyMetadata $property, array &$args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::MANY_HAS_MANY;
		$property->container = ManyHasMany::class;
		$this->processRelationshipIsMain($args, $property);
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipCascade($args, $property);
		$this->processRelationshipOrder($args, $property);
	}


	private function processRelationshipEntityProperty(array &$args, PropertyMetadata $property)
	{
		static $modifiersMap = [
			PropertyRelationshipMetadata::ONE_HAS_MANY => '1:m',
			PropertyRelationshipMetadata::ONE_HAS_ONE => '1:1',
			PropertyRelationshipMetadata::MANY_HAS_ONE => 'm:1',
			PropertyRelationshipMetadata::MANY_HAS_MANY => 'm:m',
		];
		$modifier = $modifiersMap[$property->relationship->type];
		$class = array_shift($args);

		if ($class === null) {
			throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} has not defined target entity and its property name.");
		}

		if (($pos = strpos($class, '::')) === false) {
			if (preg_match('#^[a-z0-9_\\\\]+$#i', $class) === 0) {
				throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} has invalid class name of the target entity. Use Entity::\$property format.");
			} elseif (!(isset($args['oneSided']) && $args['oneSided'])) {
				throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} has not defined target property name.");
			} else {
				$targetProperty = null;
				unset($args['oneSided']);
			}
		} else {
			$targetProperty = substr($class, $pos + 3); // skip ::$
			$class = substr($class, 0, $pos);
		}

		$entity = $this->makeFQN($class);
		if (!isset($this->entityClassesMap[$entity])) {
			throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} points to unknown '{$entity}' entity.");
		}

		$property->relationship->entity = $entity;
		$property->relationship->repository = $this->entityClassesMap[$entity];
		$property->relationship->property = $targetProperty;
	}


	private function processRelationshipCascade(array &$args, PropertyMetadata $property)
	{
		$property->relationship->cascade = $defaults = [
			'persist' => false,
			'remove' => false,
		];

		if (!isset($args['cascade'])) {
			$property->relationship->cascade['persist'] = true;
			return;
		}

		foreach ((array) $args['cascade'] as $cascade) {
			if (!isset($defaults[$cascade])) {
				throw new InvalidModifierDefinitionException();
			}
			$property->relationship->cascade[$cascade] = true;
		}
		unset($args['cascade']);
	}


	private function processRelationshipOrder(array &$args, PropertyMetadata $property)
	{
		if (!isset($args['orderBy'])) {
			return;
		}

		$order = [];
		if (is_string($args['orderBy'])) {
			$order[$args['orderBy']] = ICollection::ASC;

		} elseif (is_array($args['orderBy']) && isset($args['orderBy'][0])) {
			$order[$args['orderBy'][0]] = isset($args['orderBy'][1]) ? $args['orderBy'][1] : ICollection::ASC;

		} else {
			foreach ($args['orderBy'] as $column => $direction) {
				$order[$column] = $direction;
			}
		}

		$property->relationship->order = $order;
		unset($args['orderBy']);
	}


	private function processRelationshipIsMain(array &$args, PropertyMetadata $property)
	{
		$property->relationship->isMain = (isset($args['primary']) && $args['primary']) || (isset($args['isMain']) && $args['isMain']);
		if (isset($args['primary'])) {
			trigger_error("Primary parameter of relationship modifier in {$this->currentReflection->name}::\${$property->name} property is deprecated. Use isMain parameter.", E_USER_DEPRECATED);
		}
		unset($args['primary'], $args['isMain']);
	}


	protected function parseEnum(PropertyMetadata $property, array &$args)
	{
		$property->enum = $args;
		$args = [];
	}


	protected function parseVirtual(PropertyMetadata $property)
	{
		$property->isVirtual = true;
	}


	protected function parseContainer(PropertyMetadata $property, array &$args)
	{
		$className = $this->makeFQN(array_shift($args));
		if (!class_exists($className)) {
			throw new InvalidModifierDefinitionException("Class '$className' in {container} for {$this->currentReflection->name}::\${$property->name} property does not exist.");
		}
		$implements = class_implements($className);
		if (!isset($implements[IProperty::class])) {
			throw new InvalidModifierDefinitionException("Class '$className' in {container} for {$this->currentReflection->name}::\${$property->name} property does not implement Nextras\\Orm\\Entity\\IProperty interface.");
		}
		$property->container = $className;
	}


	protected function parseDefault(PropertyMetadata $property, array &$args)
	{
		$property->defaultValue = array_shift($args);
	}


	protected function parsePrimary(PropertyMetadata $property)
	{
		$property->isPrimary = true;
	}


	protected function parsePrimaryProxy(PropertyMetadata $property)
	{
		$property->isVirtual = true;
		$property->isPrimary = true;
		if (!$property->hasGetter && !$property->hasSetter) {
			$property->hasGetter = 'getterPrimaryProxy';
			$property->hasSetter = 'setterPrimaryProxy';
		}
	}


	protected function initPrimaryKey()
	{
		$primaryKey = array_values(array_filter(array_map(function (PropertyMetadata $metadata) {
			return $metadata->isPrimary && !$metadata->isVirtual
				? $metadata->name
				: null;
		}, $this->metadata->getProperties())));

		if (empty($primaryKey)) {
			throw new InvalidStateException("Entity {$this->reflection->name} does not have defined any primary key.");
		} elseif (!$this->metadata->hasProperty('id') || !$this->metadata->getProperty('id')->isPrimary) {
			throw new InvalidStateException("Entity {$this->reflection->name} has to have defined \$id property as {primary} or {primary-proxy}.");
		}

		$this->metadata->setPrimaryKey($primaryKey);
	}


	protected function makeFQN($name)
	{
		return AnnotationsParser::expandClassName($name, $this->currentReflection);
	}
}

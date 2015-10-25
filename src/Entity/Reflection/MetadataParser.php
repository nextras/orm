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
use Nextras\Orm\Relationships\OneHasOneDirected;


class MetadataParser implements IMetadataParser
{
	/** @var array */
	protected $modifiers = [
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
				$property->hasGetter = TRUE;
			}
			$setter = 'setter' . strtolower($name);
			if (isset($methods[$setter])) {
				$property->hasSetter = TRUE;
			}
		}
	}


	protected function loadProperties(& $fileDependencies)
	{
		$classTree = [$current = $this->reflection->name];
		while (($current = get_parent_class($current)) !== FALSE) {
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
				$isReadonly = FALSE;
			} elseif ($annotation === 'property-read') {
				$isReadonly = TRUE;
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
				$this->parseAnnotationValue($property, isset($splitted[2]) ? $splitted[2] : NULL);
			}
		}
	}


	protected function parseAnnotationTypes(PropertyMetadata $property, $typesString)
	{
		static $allTypes = [
			'array', 'bool', 'boolean', 'double', 'float', 'int', 'integer', 'mixed',
			'numeric', 'number', 'null', 'object', 'real', 'string', 'text', 'void',
			'datetime', 'datetimeimmutable', 'scalar'
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
			if (strpos($type, '[') !== FALSE) { // Class[]
				$type = 'array';
			} elseif (!in_array(strtolower($type), $allTypes, TRUE)) {
				$type = $this->makeFQN($type);
			} elseif (isset($alliases[strtolower($type)])) {
				$type = $alliases[strtolower($type)];
			}
			$types[$type] = TRUE;
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
					"Invalid modifier definition for {$this->currentReflection->name}::\${$property->name} property.", 0, $e
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
		call_user_func($callback, $property, $args);
	}


	protected function parseOneHasOne(PropertyMetadata $property, array $args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_ONE;
		$property->container = OneHasOne::class;
		$this->processRelationshipEntityProperty($args, $property);
	}


	protected function parseOneHasOneDirected(PropertyMetadata $property, array $args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED;
		$property->container = OneHasOneDirected::class;
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipPrimary($args, $property);
	}


	protected function parseOneHasMany(PropertyMetadata $property, array $args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_MANY;
		$property->container = OneHasMany::class;
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipOrder($args, $property);
	}


	protected function parseManyHasOne(PropertyMetadata $property, array $args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::MANY_HAS_ONE;
		$property->container = ManyHasOne::class;
		$this->processRelationshipEntityProperty($args, $property);
	}


	protected function parseManyHasMany(PropertyMetadata $property, array $args)
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::MANY_HAS_MANY;
		$property->container = ManyHasMany::class;
		$this->processRelationshipEntityProperty($args, $property);
		$this->processRelationshipPrimary($args, $property);
		$this->processRelationshipOrder($args, $property);
	}


	private function processRelationshipEntityProperty(array $args, PropertyMetadata $property)
	{
		static $modifiersMap = [
			PropertyRelationshipMetadata::ONE_HAS_MANY=> '1:m',
			PropertyRelationshipMetadata::ONE_HAS_ONE => '1:1',
			PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED => '1:1d',
			PropertyRelationshipMetadata::MANY_HAS_ONE => 'm:1',
			PropertyRelationshipMetadata::MANY_HAS_MANY => 'm:m',
		];
		$modifier = $modifiersMap[$property->relationship->type];
		$class = array_shift($args);

		if ($class === NULL) {
			throw new InvalidModifierDefinitionException("Relationship {" . "$modifier} in {$this->currentReflection->name}::\${$property->name} has not defined target entity and its property name.");
		}

		if (($pos = strpos($class, '::')) === FALSE) {
			throw new InvalidModifierDefinitionException("Relationship {" . "$modifier} in {$this->currentReflection->name}::\${$property->name} has not defined target property name.");
		}

		$entity = $this->makeFQN(substr($class, 0, $pos));
		if (!isset($this->entityClassesMap[$entity])) {
			throw new InvalidModifierDefinitionException("Relationship {" . "$modifier} in {$this->currentReflection->name}::\${$property->name} points to uknown '{$entity}' entity.");
		}

		$property->relationship->entity = $entity;
		$property->relationship->repository = $this->entityClassesMap[$entity];
		$property->relationship->property = substr($class, $pos + 3); // skip ::$
	}


	private function processRelationshipOrder(array $args, PropertyMetadata $property)
	{
		if (!isset($args['orderBy'])) {
			return;
		}

		$order = (array) $args['orderBy'];
		if (!isset($order[1])) {
			$order[1] = ICollection::ASC;
		}

		$property->relationship->order = $order;
	}


	private function processRelationshipPrimary(array $args, PropertyMetadata $property)
	{
		$property->relationship->isMain = isset($args['primary']) && $args['primary'];
	}


	protected function parseEnum(PropertyMetadata $property, array $args)
	{
		$property->enum = $args;
	}


	protected function parseVirtual(PropertyMetadata $property)
	{
		$property->isVirtual = TRUE;
	}


	protected function parseContainer(PropertyMetadata $property, array $args)
	{
		$className = $this->makeFQN($args[0]);
		if (!class_exists($className)) {
			throw new InvalidModifierDefinitionException("Class '$className' in {container} for {$this->currentReflection->name}::\${$property->name} property does not exist.");
		}
		$implements = class_implements($className);
		if (!isset($implements[IProperty::class])) {
			throw new InvalidModifierDefinitionException("Class '$className' in {container} for {$this->currentReflection->name}::\${$property->name} property does not implement Nextras\\Orm\\Entity\\IProperty interface.");
		}
		$property->container = $className;
	}


	protected function parseDefault(PropertyMetadata $property, array $args)
	{
		$property->defaultValue = $args[0];
	}


	protected function parsePrimary(PropertyMetadata $property)
	{
		$property->isPrimary = TRUE;
	}


	protected function parsePrimaryProxy(PropertyMetadata $property)
	{
		$property->isVirtual = TRUE;
		$property->isPrimary = TRUE;
	}


	protected function initPrimaryKey()
	{
		$primaryKey = array_values(array_filter(array_map(function (PropertyMetadata $metadata) {
			return $metadata->isPrimary && !$metadata->isVirtual
				? $metadata->name
				: NULL;
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

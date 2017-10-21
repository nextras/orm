<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use DateTime;
use DateTimeImmutable;
use Nette\Utils\Reflection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\InvalidModifierDefinitionException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\ManyHasOne;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Relationships\OneHasOne;
use ReflectionClass;


class MetadataParser implements IMetadataParser
{
	/** @var array */
	protected $modifiers = [
		'1:1' => 'parseOneHasOne',
		'1:m' => 'parseOneHasMany',
		'm:1' => 'parseManyHasOne',
		'm:m' => 'parseManyHasMany',
		'enum' => 'parseEnum',
		'virtual' => 'parseVirtual',
		'container' => 'parseContainer',
		'default' => 'parseDefault',
		'primary' => 'parsePrimary',
		'primary-proxy' => 'parsePrimaryProxy',
	];

	/** @var ReflectionClass */
	protected $reflection;

	/** @var ReflectionClass */
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


	public function parseMetadata(string $class, & $fileDependencies): EntityMetadata
	{
		$this->reflection = new ReflectionClass($class);
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
			$methods[strtolower($method->name)] = true;
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
			$reflection = new ReflectionClass($class);
			$fileDependencies[] = $reflection->getFileName();
			$this->currentReflection = $reflection;
			$this->parseAnnotations($reflection);
		}
	}


	protected function parseAnnotations(ReflectionClass $reflection)
	{
		preg_match_all(
			'~^[ \t*]* @property(|-read|-write)[ \t]+([^\s$]+)[ \t]+\$(\w+)(.*)$~um',
			(string) $reflection->getDocComment(), $matches, PREG_SET_ORDER
		);

		foreach ($matches as list(, $access, $type, $variable, $comment)) {
			$isReadonly = $access === '-read';

			$property = new PropertyMetadata();
			$property->name = $variable;
			$property->isReadonly = $isReadonly;
			$this->metadata->setProperty($property->name, $property);

			$this->parseAnnotationTypes($property, $type);
			$this->parseAnnotationValue($property, $comment);
		}
	}


	protected function parseAnnotationTypes(PropertyMetadata $property, string $typesString)
	{
		static $types = [
			'array' => true,
			'bool' => true,
			'float' => true,
			'int' => true,
			'mixed' => true,
			'null' => true,
			'object' => true,
			'string' => true,
			'text' => true,
			'scalar' => true,
		];
		static $aliases = [
			'double' => 'float',
			'real' => 'float',
			'numeric' => 'float',
			'number' => 'float',
			'integer' => 'int',
			'boolean' => 'bool',
		];

		$parsedTypes = [];
		foreach (explode('|', $typesString) as $type) {
			$typeLower = strtolower($type);
			if (strpos($type, '[') !== false) { // string[]
				$type = 'array';
			} elseif (isset($types[$typeLower])) {
				$type = $typeLower;
			} elseif (isset($aliases[$typeLower])) {
				$type = $aliases[$typeLower];
			} else {
				$type = Reflection::expandClassName($type, $this->currentReflection);
				if ($type === DateTimeImmutable::class || is_subclass_of($type, DateTimeImmutable::class)) {
					$type = 'datetime';
				} elseif ($type === DateTime::class || is_subclass_of($type, DateTime::class)) {
					throw new NotSupportedException("Type 'DateTime' in {$this->currentReflection->name}::\${$property->name} property is not supported anymore. Use DateTimeImmutable type.");
				}
			}
			$parsedTypes[$type] = true;
		}

		$property->isNullable = isset($parsedTypes['null']) || isset($parsedTypes['NULL']);
		unset($parsedTypes['null'], $parsedTypes['NULL']);
		$property->types = $parsedTypes;
	}


	protected function parseAnnotationValue(PropertyMetadata $property, string $propertyComment)
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


	protected function processPropertyModifier(PropertyMetadata $property, string $modifier, array $args)
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
		assert($property->relationship !== null);
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

		$entity = Reflection::expandClassName($class, $this->currentReflection);
		if (!isset($this->entityClassesMap[$entity])) {
			throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} points to unknown '{$entity}' entity.");
		}

		$property->relationship->entity = $entity;
		$property->relationship->repository = $this->entityClassesMap[$entity];
		$property->relationship->property = $targetProperty;
	}


	private function processRelationshipCascade(array &$args, PropertyMetadata $property)
	{
		assert($property->relationship !== null);
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
		assert($property->relationship !== null);
		if (!isset($args['orderBy'])) {
			return;
		}

		if (is_string($args['orderBy'])) {
			$order = [$args['orderBy'] => ICollection::ASC];

		} elseif (is_array($args['orderBy']) && isset($args['orderBy'][0])) {
			$order = [$args['orderBy'][0] => $args['orderBy'][1] ?? ICollection::ASC];

		} else {
			$order = $args['orderBy'];
		}

		$property->relationship->order = $order;
		unset($args['orderBy']);
	}


	private function processRelationshipIsMain(array &$args, PropertyMetadata $property)
	{
		assert($property->relationship !== null);
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
		$className = Reflection::expandClassName(array_shift($args), $this->currentReflection);
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
}

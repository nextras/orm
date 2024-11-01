<?php declare(strict_types = 1);

/** @noinspection PhpUnused */

namespace Nextras\Orm\Entity\Reflection;


use BackedEnum;
use DateTime;
use Nette\Utils\Reflection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\Embeddable\IEmbeddable;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\PropertyWrapper\BackedEnumWrapper;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\ManyHasOne;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Relationships\OneHasOne;
use Nextras\Orm\Repository\IRepository;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use function array_keys;
use function assert;
use function class_exists;
use function count;
use function is_subclass_of;
use function strlen;
use function substr;
use function trigger_error;


class MetadataParser implements IMetadataParser
{
	/** @var array<string, callable|string> */
	protected array $modifiers = [
		'1:1' => 'parseOneHasOneModifier',
		'1:m' => 'parseOneHasManyModifier',
		'm:1' => 'parseManyHasOneModifier',
		'm:m' => 'parseManyHasManyModifier',
		'enum' => 'parseEnumModifier',
		'virtual' => 'parseVirtualModifier',
		'container' => 'parseContainerModifier',
		'wrapper' => 'parseWrapperModifier',
		'default' => 'parseDefaultModifier',
		'primary' => 'parsePrimaryModifier',
		'primary-proxy' => 'parsePrimaryProxyModifier',
		'embeddable' => 'parseEmbeddableModifier',
	];

	/** @var ReflectionClass<object> */
	protected $reflection;

	/** @var ReflectionClass<object> */
	protected $currentReflection;

	/** @var EntityMetadata */
	protected $metadata;

	/** @var array<class-string<IEntity>, class-string<IRepository<IEntity>>> */
	protected $entityClassesMap;

	/** @var ModifierParser */
	protected $modifierParser;

	/** @var array<string, PropertyMetadata[]> */
	protected $classPropertiesCache = [];

	protected PhpDocParser $phpDocParser;
	protected Lexer $phpDocLexer;


	/**
	 * @param array<string, string> $entityClassesMap
	 * @param array<class-string<IEntity>, class-string<IRepository<IEntity>>> $entityClassesMap
	 */
	public function __construct(array $entityClassesMap)
	{
		$this->entityClassesMap = $entityClassesMap;
		$this->modifierParser = new ModifierParser();

		// phpdoc-parser 2.0
		if (class_exists('PHPStan\PhpDocParser\ParserConfig')) {
			$config = new ParserConfig(usedAttributes: []); // @phpstan-ignore-line
			$this->phpDocLexer = new Lexer($config); // @phpstan-ignore-line
			$constExprParser = new ConstExprParser($config); // @phpstan-ignore-line
			$typeParser = new TypeParser($config, $constExprParser); // @phpstan-ignore-line
			$this->phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser); // @phpstan-ignore-line
		} else {
			$this->phpDocLexer = new Lexer(); // @phpstan-ignore-line
			$constExprParser = new ConstExprParser(); // @phpstan-ignore-line
			$typeParser = new TypeParser($constExprParser); // @phpstan-ignore-line
			$this->phpDocParser = new PhpDocParser($typeParser, $constExprParser); // @phpstan-ignore-line
		}
	}


	/**
	 * Adds modifier processor.
	 * @return static
	 */
	public function addModifier(string $modifier, callable $processor)
	{
		$this->modifiers[strtolower($modifier)] = $processor;
		return $this;
	}


	public function parseMetadata(string $entityClass, array|null &$fileDependencies): EntityMetadata
	{
		$this->reflection = new ReflectionClass($entityClass);
		$this->metadata = new EntityMetadata($entityClass);

		$this->loadProperties($fileDependencies);
		$this->initPrimaryKey();

		if ($fileDependencies !== null) {
			$fileDependencies = array_values(array_unique($fileDependencies));
		}
		return $this->metadata;
	}


	/**
	 * @param list<string>|null $fileDependencies
	 */
	protected function loadProperties(array|null &$fileDependencies): void
	{
		$classTree = [$current = $this->reflection->name];
		while (($current = get_parent_class($current)) !== false) {
			$classTree[] = $current;
		}

		$methods = [];
		foreach ($this->reflection->getMethods() as $method) {
			$methods[strtolower($method->name)] = true;
		}

		foreach (array_reverse($classTree) as $class) {
			if (!isset($this->classPropertiesCache[$class])) {
				$traits = class_uses($class);
				foreach ($traits !== false ? $traits : [] as $traitName) {
					assert(trait_exists($traitName));
					$reflectionTrait = new ReflectionClass($traitName);
					$file = $reflectionTrait->getFileName();
					if ($file !== false) $fileDependencies[] = $file;
					$this->currentReflection = $reflectionTrait;
					$this->classPropertiesCache[$traitName] = $this->parseAnnotations($reflectionTrait, $methods);
				}

				$reflection = new ReflectionClass($class);
				$file = $reflection->getFileName();
				if ($file !== false) $fileDependencies[] = $file;
				$this->currentReflection = $reflection;
				$this->classPropertiesCache[$class] = $this->parseAnnotations($reflection, $methods);
			}

			$traits = class_uses($class);
			foreach ($traits !== false ? $traits : [] as $traitName) {
				foreach ($this->classPropertiesCache[$traitName] as $name => $property) {
					$this->metadata->setProperty($name, $property);
				}
			}

			foreach ($this->classPropertiesCache[$class] as $name => $property) {
				$this->metadata->setProperty($name, $property);
			}
		}
	}


	/**
	 * @param ReflectionClass<object> $reflection
	 * @param array<string, true> $methods
	 * @return array<string, PropertyMetadata>
	 */
	protected function parseAnnotations(ReflectionClass $reflection, array $methods): array
	{
		$docComment = $reflection->getDocComment();
		if ($docComment === false) return [];

		$tokens = new TokenIterator($this->phpDocLexer->tokenize($docComment));
		$phpDocNode = $this->phpDocParser->parse($tokens);

		$properties = [];
		foreach ($phpDocNode->getPropertyTagValues() as $propertyTagValue) {
			$property = $this->parseProperty($propertyTagValue, $reflection->getName(), $methods, isReadonly: false);
			$properties[$property->name] = $property;
		}
		foreach ($phpDocNode->getPropertyWriteTagValues() as $propertyTagValue) {
			$property = $this->parseProperty($propertyTagValue, $reflection->getName(), $methods, isReadonly: false);
			$properties[$property->name] = $property;
		}
		foreach ($phpDocNode->getPropertyReadTagValues() as $propertyTagValue) {
			$property = $this->parseProperty($propertyTagValue, $reflection->getName(), $methods, isReadonly: true);
			$properties[$property->name] = $property;
		}
		return $properties;
	}


	/**
	 * @param array<string, true> $methods
	 */
	protected function parseProperty(
		PropertyTagValueNode $propertyNode,
		string $containerClassName,
		array $methods,
		bool $isReadonly,
	): PropertyMetadata
	{
		$property = new PropertyMetadata();
		$property->name = substr($propertyNode->propertyName, 1);
		$property->containerClassname = $containerClassName;
		$property->isReadonly = $isReadonly;

		$this->parseAnnotationTypes($property, $propertyNode->type);
		$this->parseAnnotationValue($property, $propertyNode->description);
		$this->processPropertyGettersSetters($property, $methods);
		return $property;
	}


	protected function parseAnnotationTypes(PropertyMetadata $property, TypeNode $type): void
	{
		static $aliases = [
			'double' => 'float',
			'real' => 'float',
			'numeric' => 'float',
			'number' => 'float',
			'integer' => 'int',
		];

		if ($type instanceof UnionTypeNode) {
			$types = $type->types;
		} elseif ($type instanceof IntersectionTypeNode) {
			$types = $type->types;
		} else {
			$types = [$type];
		}

		$parsedTypes = [];
		foreach ($types as $subType) {
			if ($subType instanceof NullableTypeNode) {
				$property->isNullable = true;
				$subType = $subType->type;
			}
			if ($subType instanceof GenericTypeNode) {
				$subType = $subType->type;
			}


			if ($subType instanceof IdentifierTypeNode) {
				$subTypeName = $subType->name;
				if ($subTypeName === 'boolean') $subTypeName = 'bool'; // avoid expansion, bug in Nette
				$expandedSubType = Reflection::expandClassName($subTypeName, $this->currentReflection);
				$expandedSubTypeLower = strtolower($expandedSubType);

				if ($expandedSubTypeLower === 'null') {
					$property->isNullable = true;
					continue;
				}
				if ($expandedSubType === DateTime::class || is_subclass_of($expandedSubType, DateTime::class)) {
					throw new NotSupportedException("Type '{$expandedSubType}' in {$this->currentReflection->name}::\${$property->name} property is not supported anymore. Use \DateTimeImmutable or \Nextras\Dbal\Utils\DateTimeImmutable type.");
				}
				if (is_subclass_of($expandedSubType, BackedEnum::class)) {
					$property->wrapper = BackedEnumWrapper::class;
				}
				if (isset($aliases[$expandedSubTypeLower])) {
					/** @var string $expandedSubType */
					$expandedSubType = $aliases[$expandedSubTypeLower];
				}
				$parsedTypes[$expandedSubType] = true;
			} elseif ($subType instanceof ArrayTypeNode) {
				$parsedTypes['array'] = true;
			} elseif ($subType instanceof ArrayShapeNode) {
				$parsedTypes['array'] = true;
			} elseif ($subType instanceof ObjectShapeNode) {
				$parsedTypes['object'] = true;
			} else {
				throw new NotSupportedException("Type '{$type}' in {$this->currentReflection->name}::\${$property->name} property is not supported. For Nextras Orm purpose simplify it.");
			}
		}

		if (count($parsedTypes) < 1) {
			throw new NotSupportedException("Property {$this->currentReflection->name}::\${$property->name} without a type definition is not supported.");
		}
		$property->types = $parsedTypes;
	}


	protected function parseAnnotationValue(PropertyMetadata $property, string $propertyComment): void
	{
		if (strlen($propertyComment) === 0) {
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


	/**
	 * @param array<string, true> $methods
	 */
	protected function processPropertyGettersSetters(PropertyMetadata $property, array $methods): void
	{
		$getter = 'getter' . strtolower($property->name);
		if (isset($methods[$getter])) {
			$property->hasGetter = $getter;
		}
		$setter = 'setter' . strtolower($property->name);
		if (isset($methods[$setter])) {
			$property->hasSetter = $setter;
		}
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function processPropertyModifier(PropertyMetadata $property, string $modifier, array $args): void
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
		assert(is_callable($callback));
		call_user_func_array($callback, [$property, &$args]);
		if (count($args) > 0) {
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


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseOneHasOneModifier(PropertyMetadata $property, array &$args): void
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_ONE;
		$property->wrapper = OneHasOne::class;
		$this->processRelationshipIsMain($property, $args);
		$this->processRelationshipEntityProperty($property, $args);
		$this->processRelationshipCascade($property, $args);
		assert($property->relationship !== null);
		$property->isVirtual = !$property->relationship->isMain;
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseOneHasManyModifier(PropertyMetadata $property, array &$args): void
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::ONE_HAS_MANY;
		$property->wrapper = OneHasMany::class;
		$property->isVirtual = true;
		$this->processRelationshipEntityProperty($property, $args);
		$this->processRelationshipCascade($property, $args);
		$this->processRelationshipOrder($property, $args);
		$this->processRelationshipExposeCollection($property, $args);
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseManyHasOneModifier(PropertyMetadata $property, array &$args): void
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::MANY_HAS_ONE;
		$property->wrapper = ManyHasOne::class;
		$this->processRelationshipEntityProperty($property, $args);
		$this->processRelationshipCascade($property, $args);
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseManyHasManyModifier(PropertyMetadata $property, array &$args): void
	{
		$property->relationship = new PropertyRelationshipMetadata();
		$property->relationship->type = PropertyRelationshipMetadata::MANY_HAS_MANY;
		$property->wrapper = ManyHasMany::class;
		$property->isVirtual = true;
		$this->processRelationshipIsMain($property, $args);
		$this->processRelationshipEntityProperty($property, $args);
		$this->processRelationshipCascade($property, $args);
		$this->processRelationshipOrder($property, $args);
		$this->processRelationshipExposeCollection($property, $args);
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseEnumModifier(PropertyMetadata $property, array &$args): void
	{
		$property->enum = $args;
		$args = [];
	}


	protected function parseVirtualModifier(PropertyMetadata $property): void
	{
		$property->isVirtual = true;
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseContainerModifier(PropertyMetadata $property, array &$args): void
	{
		trigger_error("Property modifier {container} is depraceted; rename it to {wrapper} modifier.", E_USER_DEPRECATED);
		$this->parseWrapperModifier($property, $args);
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseWrapperModifier(PropertyMetadata $property, array &$args): void
	{
		$className = Reflection::expandClassName(array_shift($args), $this->currentReflection);
		if (!class_exists($className)) {
			throw new InvalidModifierDefinitionException("Class '$className' in {wrapper} for {$this->currentReflection->name}::\${$property->name} property does not exist.");
		}
		$implements = class_implements($className);
		if ($implements !== false && !isset($implements[IProperty::class])) {
			throw new InvalidModifierDefinitionException("Class '$className' in {wrapper} for {$this->currentReflection->name}::\${$property->name} property does not implement Nextras\\Orm\\Entity\\IProperty interface.");
		}
		$property->wrapper = $className;
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function parseDefaultModifier(PropertyMetadata $property, array &$args): void
	{
		$property->defaultValue = array_shift($args);
	}


	protected function parsePrimaryModifier(PropertyMetadata $property): void
	{
		$property->isPrimary = true;
	}


	protected function parsePrimaryProxyModifier(PropertyMetadata $property): void
	{
		$property->isVirtual = true;
		$property->isPrimary = true;
		if ($property->hasGetter === null && $property->hasSetter === null) {
			$property->hasGetter = 'getterPrimaryProxy';
			$property->hasSetter = 'setterPrimaryProxy';
		}
	}


	protected function parseEmbeddableModifier(PropertyMetadata $property): void
	{
		if (count($property->types) !== 1) {
			$num = count($property->types);
			throw new InvalidModifierDefinitionException("Embeddable modifer requries only one class type definition, optionally nullable. $num types detected in {$this->currentReflection->name}::\${$property->name} property.");
		}
		$className = array_keys($property->types)[0];
		if (!class_exists($className)) {
			throw new InvalidModifierDefinitionException("Class '$className' in {embeddable} for {$this->currentReflection->name}::\${$property->name} property does not exist.");
		}
		if (!is_subclass_of($className, IEmbeddable::class)) {
			throw new InvalidModifierDefinitionException("Class '$className' in {embeddable} for {$this->currentReflection->name}::\${$property->name} property does not implement " . IEmbeddable::class . " interface.");
		}

		$property->wrapper = EmbeddableContainer::class;
		$property->args[EmbeddableContainer::class] = ['class' => $className];
	}


	protected function initPrimaryKey(): void
	{
		if ($this->reflection->isSubclassOf(IEmbeddable::class)) {
			return;
		}

		$primaryKey = [];
		foreach ($this->metadata->getProperties() as $metadata) {
			if ($metadata->isPrimary && !$metadata->isVirtual) {
				$primaryKey[] = $metadata->name;
			}
		}

		if (count($primaryKey) === 0) {
			throw new InvalidStateException("Entity {$this->reflection->name} does not have defined any primary key.");
		} elseif (!$this->metadata->hasProperty('id') || !$this->metadata->getProperty('id')->isPrimary) {
			throw new InvalidStateException("Entity {$this->reflection->name} has to have defined \$id property as {primary} or {primary-proxy}.");
		}

		$this->metadata->setPrimaryKey($primaryKey);
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function processRelationshipEntityProperty(PropertyMetadata $property, array &$args): void
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

		$pos = strpos($class, '::');
		if ($pos === false) {
			if (preg_match('#^[a-z0-9_\\\\]+$#i', $class) !== 1) {
				throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} has invalid class name of the target entity. Use Entity::\$property format.");
			} elseif (!(isset($args['oneSided']) && $args['oneSided'] === true)) {
				throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} has not defined target property name.");
			} else {
				$targetProperty = null;
				unset($args['oneSided']);
			}
		} else {
			$targetProperty = substr($class, $pos + 3); // skip ::$
			assert($targetProperty !== false); // @phpstan-ignore-line
			$class = substr($class, 0, $pos);

			if (isset($args['oneSided'])) {
				throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} has set oneSided property but it also specifies target property \${$targetProperty}.");
			}
		}

		/** @var class-string<IEntity> $entity */
		$entity = Reflection::expandClassName($class, $this->currentReflection);
		if (!isset($this->entityClassesMap[$entity])) {
			throw new InvalidModifierDefinitionException("Relationship {{$modifier}} in {$this->currentReflection->name}::\${$property->name} points to unknown '{$entity}' entity. Don't forget to return it in IRepository::getEntityClassNames() and register its repository.");
		}

		$property->relationship->entity = $entity;
		$property->relationship->repository = $this->entityClassesMap[$entity];
		$property->relationship->property = $targetProperty;
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function processRelationshipCascade(PropertyMetadata $property, array &$args): void
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


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function processRelationshipOrder(PropertyMetadata $property, array &$args): void
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


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function processRelationshipExposeCollection(PropertyMetadata $property, array &$args): void
	{
		if (isset($args['exposeCollection']) && $args['exposeCollection'] === true) {
			$property->args[HasMany::class]['exposeCollection'] = true;
		}
		unset($args['exposeCollection']);
	}


	/**
	 * @param array<int|string, mixed> $args
	 */
	protected function processRelationshipIsMain(PropertyMetadata $property, array &$args): void
	{
		assert($property->relationship !== null);
		$property->relationship->isMain = isset($args['isMain']) && $args['isMain'] === true;
		unset($args['isMain']);
	}
}

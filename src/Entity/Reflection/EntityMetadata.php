<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nette\SmartObject;
use Nextras\Orm\Entity\Helpers\Typos;
use Nextras\Orm\Exception\InvalidArgumentException;


/**
 * @property-read string $className
 */
class EntityMetadata
{
	use SmartObject;

	/** @var list<string> */
	private array $primaryKey = [];

	/** @var array<string, PropertyMetadata> */
	private array $properties = [];

	/** @var array<string, true>|null lazily built map of non-virtual property names */
	private ?array $nonVirtualPropertyNames = null;


	/**
	 * @param class-string $className
	 */
	public function __construct(
		private readonly string $className,
	)
	{
	}


	public function getClassName(): string
	{
		return $this->className;
	}


	/**
	 * @param list<string> $primaryKey
	 * @return static
	 */
	public function setPrimaryKey(array $primaryKey): EntityMetadata
	{
		$this->primaryKey = $primaryKey;
		return $this;
	}


	/**
	 * @return list<string>
	 */
	public function getPrimaryKey(): array
	{
		return $this->primaryKey;
	}


	public function getProperty(string $name): PropertyMetadata
	{
		if (!isset($this->properties[$name])) {
			$closest = Typos::getClosest($name, array_keys($this->properties));
			throw new InvalidArgumentException(
				"Undefined property {$this->className}::\${$name}"
				. ($closest !== null ? ", did you mean \$$closest?" : '.')
			);
		}

		return $this->properties[$name];
	}


	public function hasProperty(string $name): bool
	{
		return isset($this->properties[$name]);
	}


	public function setProperty(string $name, PropertyMetadata $property): void
	{
		$this->properties[$name] = $property;
		$this->nonVirtualPropertyNames = null;
	}


	/**
	 * @return array<string, PropertyMetadata>
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}


	/**
	 * Returns names of non-virtual properties as keys of the returned array (values are true).
	 * @return array<string, true>
	 * @internal
	 */
	public function getNonVirtualPropertyNames(): array
	{
		if ($this->nonVirtualPropertyNames === null) {
			$this->nonVirtualPropertyNames = [];
			foreach ($this->properties as $name => $property) {
				if (!$property->isVirtual) {
					$this->nonVirtualPropertyNames[$name] = true;
				}
			}
		}
		return $this->nonVirtualPropertyNames;
	}
}

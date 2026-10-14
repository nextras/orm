<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\Container;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IDependencyProvider;
use ReflectionClass;
use function str_ends_with;
use function stripos;
use function strncasecmp;


class DependencyProvider implements IDependencyProvider
{
	/** @var array<class-string, bool> */
	private array $hasInjectionPoints = [];


	public function __construct(
		private readonly Container $container,
	)
	{
	}


	public function injectDependencies(IEntity $entity): void
	{
		// Container::callInjects() reflects the entity class on every call, which is a significant
		// per-entity cost during hydration. Most entities do not use injection at all, so it is skipped
		// for classes without any possible injection point.
		if ($this->hasInjectionPoints[$entity::class] ??= self::hasInjectionPoints($entity::class)) {
			$this->container->callInjects($entity);
		}
	}


	/**
	 * Conservatively detects whether Nette DI could inject anything into the class: any method whose
	 * name starts with "inject" or any property with an "Inject" attribute or an "@inject" annotation.
	 * It is a superset of the Nette DI detection, so a false positive only leads to calling callInjects().
	 * @param class-string $class
	 */
	private static function hasInjectionPoints(string $class): bool
	{
		$reflection = new ReflectionClass($class);
		foreach ($reflection->getMethods() as $method) {
			if (strncasecmp($method->name, 'inject', 6) === 0) {
				return true;
			}
		}
		foreach ($reflection->getProperties() as $property) {
			foreach ($property->getAttributes() as $attribute) {
				if (str_ends_with($attribute->getName(), 'Inject')) {
					return true;
				}
			}
			$docComment = $property->getDocComment();
			if ($docComment !== false && stripos($docComment, '@inject') !== false) {
				return true;
			}
		}
		return false;
	}
}

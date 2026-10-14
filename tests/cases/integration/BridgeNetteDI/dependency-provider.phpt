<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Mockery;
use Nette\DI\Attributes\Inject;
use Nette\DI\Container;
use Nextras\Orm\Bridges\NetteDI\DependencyProvider;
use Nextras\Orm\Entity\Entity;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../../../bootstrap.php';


class PlainEntity extends Entity
{
}


class MethodInjectEntity extends Entity
{
	public ?stdClass $service = null;


	public function injectService(stdClass $service): void
	{
		$this->service = $service;
	}
}


class AttributeInjectEntity extends Entity
{
	#[Inject]
	public stdClass $service;
}


class AnnotationInjectEntity extends Entity
{
	/** @inject */
	public stdClass $service;
}


class ParentInjectEntity extends MethodInjectEntity
{
}


class DependencyProviderTest extends TestCase
{
	public function testSkipsEntitiesWithoutInjectionPoints(): void
	{
		$container = Mockery::mock(Container::class);
		$container->shouldReceive('callInjects')->never();

		$provider = new DependencyProvider($container);
		$provider->injectDependencies($this->create(PlainEntity::class));
		$provider->injectDependencies($this->create(PlainEntity::class));
		Assert::true(true);
	}


	public function testCallsInjectsForEntitiesWithInjectionPoints(): void
	{
		$entities = [
			$this->create(MethodInjectEntity::class),
			$this->create(AttributeInjectEntity::class),
			$this->create(AnnotationInjectEntity::class),
			$this->create(ParentInjectEntity::class),
			$this->create(ParentInjectEntity::class),
		];
		$container = Mockery::mock(Container::class);
		foreach ($entities as $entity) {
			$container->shouldReceive('callInjects')->once()->with($entity);
		}

		$provider = new DependencyProvider($container);
		foreach ($entities as $entity) {
			$provider->injectDependencies($entity);
		}
		Assert::true(true);
	}


	public function testRealInjection(): void
	{
		$service = new stdClass();
		$container = new Container();
		// set via reflection: the property is untyped in older nette/di and typed in newer, a subclass can't fit both
		(new ReflectionProperty(Container::class, 'wiring'))->setValue($container, [stdClass::class => [['service']]]);
		$container->addService('service', $service);
		$provider = new DependencyProvider($container);

		$methodEntity = $this->create(MethodInjectEntity::class);
		$provider->injectDependencies($methodEntity);
		Assert::same($service, $methodEntity->service);

		$attributeEntity = $this->create(AttributeInjectEntity::class);
		$provider->injectDependencies($attributeEntity);
		Assert::same($service, $attributeEntity->service);

		$annotationEntity = $this->create(AnnotationInjectEntity::class);
		$provider->injectDependencies($annotationEntity);
		Assert::same($service, $annotationEntity->service);
	}


	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	private function create(string $class): object
	{
		return (new ReflectionClass($class))->newInstanceWithoutConstructor();
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}
}


$test = new DependencyProviderTest();
$test->run();

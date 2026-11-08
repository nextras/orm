<?php declare(strict_types = 1);

/**
 * @testCase
 * @phpVersion >= 8.4
 */

namespace NextrasTests\Orm\Entity\PropertyHooks;


use DateTimeImmutable;
use Mockery;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Extension;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\Repository;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/fixtures/PropertyHookEntity.php';


class EntityPropertyHooksTest extends TestCase
{
	public function testMetadata(): void
	{
		$metadata = (new HookEntity())->getMetadata();
		$age = $metadata->getProperty('age');
		Assert::true($age->hasPropertyHooks);
		Assert::true($age->isVirtual);
		Assert::true($age->isReadonly);
		Assert::same(['int' => true], $age->types);
		Assert::null($age->wrapper);
		Assert::false($metadata->getProperty('displayName')->isReadonly);
		Assert::true($metadata->getProperty('restricted')->isReadonly);
		Assert::same([DateTimeImmutable::class => true], $metadata->getProperty('birthDate')->types);
		Assert::true($metadata->getProperty('birthDate')->isNullable);
		Assert::null($metadata->getProperty('birthDate')->wrapper);
		Assert::same(['string' => true, 'int' => true], $metadata->getProperty('identifier')->types);
		Assert::same(['mixed' => true], $metadata->getProperty('untyped')->types);
		Assert::true($metadata->getProperty('untyped')->isNullable);
		Assert::same([HookEntity::class => true], $metadata->getProperty('myself')->types);
		Assert::false($metadata->hasProperty('undocumented'));
		Assert::false($metadata->hasProperty('backed'));
		Assert::false($metadata->hasProperty('internal'));
	}


	public function testReadingAndWriting(): void
	{
		$entity = new HookEntity();
		$entity->id = 1;
		$entity->name = 'Jon';
		Assert::same(0, $entity->age);
		Assert::same(0, $entity->getValue('age'));
		Assert::true($entity->hasValue('age'));
		Assert::false(isset($entity->birthDate));
		Assert::false($entity->hasValue('birthDate'));
		Assert::null($entity->getValue('birthDate'));
		Assert::null($entity->getValue('untyped'));
		$entity->born = new DateTimeImmutable('2000-01-01');
		Assert::same(26, $entity->getValue('age'));
		Assert::same(26, $entity->getRawValue('age'));
		Assert::true($entity->hasValue('birthDate'));
		Assert::same($entity->born, $entity->getValue('birthDate'));
		Assert::same($entity, $entity->getValue('myself'));

		$entity->onPersist(1);
		$entity->displayName = 'Jan';
		Assert::true($entity->isModified('name'));
		Assert::same('JAN', $entity->getValue('displayName'));
		$entity->setValue('displayName', 'Eva');
		Assert::same('EVA', $entity->displayName);
		$entity->setRawValue('displayName', 'Bob');
		Assert::same('BOB', $entity->getRawValue('displayName'));
		Assert::same(3, $entity->nameLength);
		$this->assertReadOnly($entity, 'age');
		$this->assertReadOnly($entity, 'restricted');
	}


	public function testExportAndHydration(): void
	{
		$entity = new HookEntity();
		$entity->onLoad(['id' => 1, 'name' => 'Jon', 'born' => null, 'age' => 999]);
		Assert::same(0, $entity->getValue('age'));
		Assert::same(['id' => 1, 'name' => 'Jon', 'born' => null], $entity->getRawValues());
		$data = $entity->toArray(skipProperties: ['myself']);
		Assert::same(0, $data['age']);
		Assert::same('JON', $data['displayName']);
		Assert::null($data['birthDate']);
		Assert::null($data['untyped']);
		$clone = clone $entity;
		Assert::same('JON', $clone->getValue('displayName'));
	}


	public function testInheritance(): void
	{
		$entity = new ChildHookEntity();
		$entity->name = 'Jon';
		Assert::same(42, $entity->getValue('age'));
		Assert::same('JON', $entity->getValue('displayName'));
		Assert::same([HookEntity::class => true], $entity->getMetadata()->getProperty('myself')->types);
	}


	public function testExtensions(): void
	{
		$extension = new class extends Extension {
			public function configureEntityPropertyMetadata(
				EntityMetadata $entityMetadata,
				PropertyMetadata $propertyMetadata,
				TypeNode $propertyType,
			): void
			{
				if ($propertyMetadata->isVirtual) {
					$propertyMetadata->args['nativeType'] = (string) $propertyType;
				}
			}
		};
		$dependencies = [];
		$parser = new MetadataParser([], [$extension]);
		$metadata = $parser->parseMetadata(HookEntity::class, $dependencies);
		Assert::same(['nativeType' => 'int'], $metadata->getProperty('age')->args);
		Assert::contains(__DIR__ . '/fixtures/PropertyHookEntity.php', $dependencies ?? []);
	}


	public function testCollectionQueries(): void
	{
		$younger = new HookEntity();
		$younger->born = new DateTimeImmutable('2000-01-01');
		$older = new HookEntity();
		$older->born = new DateTimeImmutable('1980-01-01');
		$mapper = Mockery::mock(IMapper::class);
		$mapper->shouldReceive('setRepository')->once();
		$repository = Mockery::mock(Repository::class, [$mapper])->makePartial();
		$repository->shouldReceive('getModel')->andReturn(Mockery::mock(IModel::class));
		$repository->shouldReceive('getEntityMetadata')->andReturn($younger->getMetadata());
		$collection = new ArrayCollection([$younger, $older], $repository);
		Assert::same([$older], $collection->findBy(['age>' => 30])->fetchAll());
		Assert::same([$older, $younger], $collection->orderBy('age', 'DESC')->fetchAll());
		Assert::throws(
			fn() => $collection->findBy(['undocumented' => 1])->fetchAll(),
			InvalidArgumentException::class,
			'Undefined property %A%::$undocumented.',
		);
		Mockery::close();
	}


	public function testInvalidAttributes(): void
	{
		$parser = new MetadataParser([]);
		foreach ([InvalidBackedHookEntity::class, InvalidProtectedHookEntity::class, InvalidWriteOnlyHookEntity::class] as $class) {
			$dependencies = [];
			Assert::throws(
				fn() => $parser->parseMetadata($class, $dependencies),
				NotSupportedException::class,
				'Attribute VirtualEntityProperty on %A%::$invalid requires a public virtual property with a get hook.',
			);
		}
	}


	private function assertReadOnly(HookEntity $entity, string $name): void
	{
		Assert::throws(fn() => $entity->setValue($name, 12), InvalidArgumentException::class, "Property '$name' is read-only.");
	}
}


(new EntityPropertyHooksTest())->run();

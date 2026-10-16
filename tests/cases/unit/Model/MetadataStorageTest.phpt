<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Model;


use Mockery;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\IMetadataParser;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Repository\Repository;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @property int $id {primary}
 * @property MetaBar|null $bar {m:1 MetaBar::$foos}
 */
class MetaFoo extends Entity
{
}


/**
 * @property int $id {primary}
 * @property OneHasMany<MetaFoo> $foos {1:m MetaFoo::$bar}
 */
class MetaBar extends Entity
{
}


/**
 * @property int $id {primary}
 */
class MetaBaz extends Entity
{
}


/**
 * @extends Repository<MetaFoo>
 */
class MetaFoosRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [MetaFoo::class];
	}
}


/**
 * @extends Repository<MetaBar>
 */
class MetaBarsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [MetaBar::class];
	}
}


/**
 * @extends Repository<MetaBaz>
 */
class MetaBazsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [MetaBaz::class];
	}
}


class CountingMetadataParserFactory implements IMetadataParserFactory
{
	public int $count = 0;


	public function create(array $entityClassesMap): IMetadataParser
	{
		$this->count++;
		return (new MetadataParserFactory())->create($entityClassesMap);
	}
}


class MetadataStorageTest extends TestCase
{
	protected function setUp()
	{
		// do not call default setup which instantiate ORM model
	}


	public function testExceptions(): void
	{
		Assert::throws(function (): void {
			MetadataStorage::get(Book::class);
		}, InvalidStateException::class);

		parent::setUp();

		$metadata = MetadataStorage::get(Book::class);
		Assert::type(EntityMetadata::class, $metadata);

		Assert::throws(function (): void {
			MetadataStorage::get('NextrasTests\Orm\InvalidEntityName');
		}, InvalidArgumentException::class);
	}


	public function testLoadedOnFirstUse(): void
	{
		$parserFactory = new CountingMetadataParserFactory();
		$this->createStorage($parserFactory, [
			MetaFoo::class => MetaFoosRepository::class,
			MetaBar::class => MetaBarsRepository::class,
		]);
		Assert::same(0, $parserFactory->count);
		Assert::same([], $this->getStaticMetadata());

		$foo = MetadataStorage::get(MetaFoo::class);
		Assert::same(1, $parserFactory->count);
		Assert::same(MetadataStorage::get(MetaBar::class), $foo->getProperty('bar')->relationship?->entityMetadata);

		MetadataStorage::get(MetaBar::class);
		Assert::same(1, $parserFactory->count);
	}


	public function testLoadedPerEntity(): void
	{
		$parserFactory = new CountingMetadataParserFactory();
		$this->createStorage($parserFactory, [
			MetaFoo::class => MetaFoosRepository::class,
			MetaBar::class => MetaBarsRepository::class,
		]);

		$foo = MetadataStorage::get(MetaFoo::class);
		Assert::same([MetaFoo::class], array_keys($this->getStaticMetadata()));

		// related entity metadata are loaded on the first access
		$relationship = $foo->getProperty('bar')->relationship;
		Assert::notNull($relationship);
		Assert::true(isset($relationship->entityMetadata));
		$bar = $relationship->entityMetadata;
		Assert::same([MetaFoo::class, MetaBar::class], array_keys($this->getStaticMetadata()));
		Assert::same(MetadataStorage::get(MetaBar::class), $bar);
		Assert::same($foo, $bar->getProperty('foos')->relationship?->entityMetadata);
		Assert::same(1, $parserFactory->count);
	}


	public function testSerializedRelationshipIsResolvedLazily(): void
	{
		$this->createStorage(new CountingMetadataParserFactory(), [
			MetaFoo::class => MetaFoosRepository::class,
			MetaBar::class => MetaBarsRepository::class,
		])->getLoadedMetadata();

		$serialized = serialize(MetadataStorage::get(MetaFoo::class));
		// the target entity metadata are not serialized
		Assert::same(1, substr_count($serialized, 'O:' . strlen(EntityMetadata::class) . ':"' . EntityMetadata::class . '"'));
		$foo = unserialize($serialized);
		Assert::type(EntityMetadata::class, $foo);
		Assert::same(MetadataStorage::get(MetaBar::class), $foo->getProperty('bar')->relationship?->entityMetadata);
	}


	public function testLoadedByGetLoadedMetadata(): void
	{
		$parserFactory = new CountingMetadataParserFactory();
		$storage = $this->createStorage($parserFactory, [
			MetaFoo::class => MetaFoosRepository::class,
			MetaBar::class => MetaBarsRepository::class,
		]);

		Assert::same([MetaFoo::class, MetaBar::class], array_keys($storage->getLoadedMetadata()));
		Assert::same(1, $parserFactory->count);
	}


	public function testMultipleStorages(): void
	{
		$parserFactory1 = new CountingMetadataParserFactory();
		$this->createStorage($parserFactory1, [
			MetaFoo::class => MetaFoosRepository::class,
			MetaBar::class => MetaBarsRepository::class,
		]);
		$parserFactory2 = new CountingMetadataParserFactory();
		$this->createStorage($parserFactory2, [
			MetaBaz::class => MetaBazsRepository::class,
		]);

		Assert::type(EntityMetadata::class, MetadataStorage::get(MetaBaz::class));
		Assert::same(1, $parserFactory2->count);
		Assert::type(EntityMetadata::class, MetadataStorage::get(MetaFoo::class));
		Assert::same(1, $parserFactory1->count);

		Assert::throws(function (): void {
			MetadataStorage::get('NextrasTests\Orm\InvalidEntityName');
		}, InvalidArgumentException::class);
	}


	public function testAlreadyLoadedStorageIsNotLoadedAgain(): void
	{
		$map = [
			MetaFoo::class => MetaFoosRepository::class,
			MetaBar::class => MetaBarsRepository::class,
		];
		$parserFactory1 = new CountingMetadataParserFactory();
		$this->createStorage($parserFactory1, $map)->getLoadedMetadata();
		$metadata = MetadataStorage::get(MetaFoo::class);

		$parserFactory2 = new CountingMetadataParserFactory();
		$storage2 = $this->createStorage($parserFactory2, $map);
		Assert::same($metadata, $storage2->getLoadedMetadata()[MetaFoo::class]);
		Assert::same(0, $parserFactory2->count);
	}


	public function testRegisteredLoader(): void
	{
		$parserFactory = new CountingMetadataParserFactory();
		$calls = 0;
		MetadataStorage::registerLoader(function () use ($parserFactory, &$calls): void {
			$calls++;
			$this->createStorage($parserFactory, [
				MetaFoo::class => MetaFoosRepository::class,
				MetaBar::class => MetaBarsRepository::class,
			])->getLoadedMetadata();
		});
		Assert::same(0, $calls);

		Assert::type(EntityMetadata::class, MetadataStorage::get(MetaBar::class));
		Assert::type(EntityMetadata::class, MetadataStorage::get(MetaFoo::class));
		Assert::same(1, $calls);
		Assert::same(1, $parserFactory->count);
	}


	public function testFailedLoadingIsRetried(): void
	{
		$this->createStorage(
			new CountingMetadataParserFactory(),
			[MetaFoo::class => MetaFoosRepository::class, MetaBar::class => MetaBarsRepository::class],
			hasRepositories: false,
		);

		for ($i = 0; $i < 2; $i++) {
			Assert::exception(function (): void {
				MetadataStorage::get(MetaFoo::class);
			}, InvalidStateException::class, '%a%points to unknown%a%');
		}
	}


	/**
	 * @param array<class-string, class-string> $map
	 */
	private function createStorage(
		IMetadataParserFactory $parserFactory,
		array $map,
		bool $hasRepositories = true,
	): MetadataStorage
	{
		$repositoryLoader = Mockery::mock(IRepositoryLoader::class);
		$repositoryLoader->shouldReceive('hasRepository')->andReturn($hasRepositories);
		/** @var array<class-string<IEntity>, class-string<IRepository<IEntity>>> $map */
		return new MetadataStorage($map, new Cache(new MemoryStorage()), $parserFactory, $repositoryLoader);
	}


	/**
	 * @return array<string, EntityMetadata>
	 */
	private function getStaticMetadata(): array
	{
		return (new \ReflectionProperty(MetadataStorage::class, 'metadata'))->getValue();
	}
}


$test = new MetadataStorageTest();
$test->run();

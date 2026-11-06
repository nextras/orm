<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships\OneSided;


use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Memory\ArrayMapper;
use Nextras\Orm\Model\SimpleModelFactory;
use Nextras\Orm\Relationships\HasOne;
use Nextras\Orm\Repository\Repository;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneSidedTest extends TestCase
{
	/**
	 * @dataProvider relationshipProvider
	 * @param 'entity'|'id'|'new'|'null' $replacement
	 */
	public function testReplaceRemovedTarget(string $propertyName, bool $loaded, string $replacement): void
	{
		$owners = new OwnersRepository(new MemoryMapper());
		$targets = new TargetsRepository(new MemoryMapper());
		$model = (new SimpleModelFactory(new Cache(new MemoryStorage()), [
			'owners' => $owners,
			'targets' => $targets,
		]))->create();

		$oldTarget = new Target();
		$newTarget = new Target();
		$targets->persistAndFlush($oldTarget);
		$targets->persistAndFlush($newTarget);
		$owner = new Owner();
		$owner->setValue($propertyName, $oldTarget);
		$owners->persistAndFlush($owner);
		$oldId = $oldTarget->id;
		$newId = $newTarget->id;
		$ownerId = $owner->id;
		$model->clear();

		$owner = $owners->getByIdChecked($ownerId);
		$oldTarget = $targets->getByIdChecked($oldId);
		$newTarget = $targets->getByIdChecked($newId);
		$property = $owner->getProperty($propertyName);
		assert($property instanceof HasOne);
		Assert::false($property->isLoaded());
		if ($loaded) {
			Assert::same($oldTarget, $owner->getValue($propertyName));
		}
		$targets->removeAndFlush($oldTarget);

		$value = match ($replacement) {
			'entity' => $newTarget,
			'id' => $newId,
			'new' => new Target(),
			'null' => null,
		};
		$owner->setValue($propertyName, $value);
		Assert::true($owner->isModified());
		Assert::true($property->isModified());
		Assert::same($replacement === 'id' ? $newTarget : $value, $owner->getValue($propertyName));
		$owners->persistAndFlush($owner);
		$expectedId = $value instanceof Target ? $value->id : $value;
		Assert::same($expectedId, $owner->getRawValue($propertyName));

		$model->clear();
		$owner = $owners->getByIdChecked($ownerId);
		Assert::same($expectedId, $owner->getRawValue($propertyName));
		Assert::same($expectedId === null ? null : $targets->getByIdChecked($expectedId), $owner->getValue($propertyName));
		Assert::null($targets->getById($oldId));
	}


	/**
	 * @return list<array{string, bool, 'entity'|'id'|'new'|'null'}>
	 */
	protected function relationshipProvider(): array
	{
		$cases = [];
		foreach (['oneTarget', 'manyTarget'] as $propertyName) {
			foreach ([false, true] as $loaded) {
				foreach (['entity', 'id', 'new', 'null'] as $replacement) {
					$cases[] = [$propertyName, $loaded, $replacement];
				}
			}
		}
		return $cases;
	}
}


/**
 * @property int         $id         {primary}
 * @property Target|null $oneTarget  {1:1 Target, isMain=true, oneSided=true}
 * @property Target|null $manyTarget {m:1 Target, oneSided=true}
 */
class Owner extends Entity
{
}


/**
 * @property int $id {primary}
 */
class Target extends Entity
{
}


/**
 * @extends Repository<Owner>
 */
class OwnersRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Owner::class];
	}
}


/**
 * @extends Repository<Target>
 */
class TargetsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Target::class];
	}
}


/**
 * @template E of IEntity
 * @extends ArrayMapper<E>
 */
class MemoryMapper extends ArrayMapper
{
	/** @var array{0?: array<int|string, mixed>, 1?: array<string, array<int|string, mixed>>} */
	private array $stored = [];


	protected function readData(): array
	{
		return $this->stored;
	}


	protected function saveData(array $data): void
	{
		$this->stored = $data;
	}
}


$test = new RelationshipOneSidedTest();
$test->run();

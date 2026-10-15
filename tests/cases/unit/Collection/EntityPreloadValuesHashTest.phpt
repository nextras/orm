<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Collection;


use Mockery;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityPreloadValuesHashTest extends TestCase
{
	public function testHashesAreCachedPerPropertyAndBatch(): void
	{
		foreach ([false, true] as $multi) {
			$metadata = Mockery::mock(EntityMetadata::class);
			$metadata->shouldReceive('hasProperty')->with('id')->once()->andReturn(true);
			$metadata->shouldReceive('hasProperty')->with('author')->once()->andReturn(true);
			$entity = Mockery::mock(Entity::class);
			$entity->shouldReceive('getMetadata')->twice()->andReturn($metadata);
			$entity->shouldReceive('getRawValue')->with('id')->once()->andReturn([1, '2']);
			$entity->shouldReceive('getRawValue')->with('author')->once()->andReturn('1');
			$iterator = $multi ? new MultiEntityIterator([1 => [$entity]]) : new EntityIterator([$entity]);
			$other = $multi ? new MultiEntityIterator([]) : new EntityIterator([]);

			$idHash = hash('xxh3', json_encode([[1, '2']], JSON_THROW_ON_ERROR));
			$authorHash = hash('xxh3', json_encode(['1'], JSON_THROW_ON_ERROR));
			Assert::same($idHash, $iterator->getPreloadValuesHash('id'));
			Assert::same($authorHash, $iterator->getPreloadValuesHash('author'));
			Assert::same($idHash, $iterator->getPreloadValuesHash('id'));
			Assert::same([[1, '2']], $iterator->getPreloadValues('id'));
			Assert::same(hash('xxh3', json_encode([], JSON_THROW_ON_ERROR)), $other->getPreloadValuesHash('id'));
			Assert::same($idHash, $iterator->getPreloadValuesHash('id'));
		}
	}


	public function testClonesShareComputedHashes(): void
	{
		$iterator = new class([]) extends MultiEntityIterator {
			public int $loads = 0;


			public function getPreloadValues(string $property): array
			{
				$this->loads++;
				return [1, '2'];
			}
		};
		$clone = clone $iterator;
		$clone->setDataIndex(1);
		$iterator->setDataIndex(2);

		$hash = $clone->getPreloadValuesHash('id');
		Assert::same($hash, $iterator->getPreloadValuesHash('id'));
		Assert::same($hash, $clone->getPreloadValuesHash('id'));
		Assert::same(0, $iterator->loads);
		Assert::same(1, $clone->loads);
	}
}


$test = new EntityPreloadValuesHashTest();
$test->run();

<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityPreloadContainerTest extends DataTestCase
{
	public function testCombination(): void
	{
		foreach ($this->orm->books->findAll() as $book) {
			Assert::true($book->getPreloadContainer() !== null);
		}

		Assert::null($this->orm->books->getByIdChecked(1)->getPreloadContainer());

		foreach ($this->orm->books->findAll() as $book) {
			Assert::true($book->getPreloadContainer() !== null);
		}
	}


	public function testWithEntityWithInvalidRelationshipState(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		$tagFollowers = $author->tagFollowers->toCollection()->fetchAll();
		$firstTagFollower = $tagFollowers[0];
		$secondTagFollower = $tagFollowers[1];

		// making sure that removing firstTagFollower do not load relationships for the secondTagFollower
		$firstTagFollower->setPreloadContainer(null);
		// this will null relationships in TagFollower making them unusable for preloading for second tag follower
		$this->orm->remove($firstTagFollower);

		Assert::same(3, $secondTagFollower->tag->id);
	}
}


$test = new EntityPreloadContainerTest();
$test->run();


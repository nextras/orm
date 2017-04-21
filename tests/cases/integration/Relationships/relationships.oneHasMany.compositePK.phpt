<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasManyCompositePkTest extends DataTestCase
{

	public function testBasic()
	{
		/** @var TagFollower $tagFollower */
		$tagFollower = $this->orm->tagFollowers->getBy(['tag' => 3, 'author' => 1]);

		Assert::same($tagFollower->tag->name, 'Tag 3');
		Assert::same($tagFollower->author->name, 'Writer 1');
	}


	public function testHasMany()
	{
		/** @var Author $author */
		$author = $this->orm->authors->getById(1);
		Assert::same(2, $author->tagFollowers->count());
		Assert::same(2, $author->tagFollowers->countStored());
	}


	public function testLimit()
	{
		$tagFollower = new TagFollower();
		$this->orm->tagFollowers->attach($tagFollower);
		$tagFollower->tag = 2;
		$tagFollower->author = 1;
		$this->orm->tagFollowers->persistAndFlush($tagFollower);

		$tagFollowers = [];
		/** @var Author[] $authors */
		$authors = $this->orm->authors->findAll()->orderBy('id');

		foreach ($authors as $author) {
			foreach ($author->tagFollowers->get()->limitBy(2)->orderBy('tag', ICollection::DESC) as $tagFollower) {
				$tagFollowers[] = $tagFollower->getRawValue('tag');
			}
		}

		Assert::same([3, 2, 2], $tagFollowers);
	}


	public function testRemoveHasMany()
	{
		$tagFollower = $this->orm->tagFollowers->getBy(['tag' => 3, 'author' => 1]);
		$this->orm->tagFollowers->removeAndFlush($tagFollower);

		Assert::same(1, $this->orm->authors->getById(1)->tagFollowers->count());
		Assert::same(1, $this->orm->authors->getById(1)->tagFollowers->countStored());
	}

}


$test = new RelationshipOneHasManyCompositePkTest($dic);
$test->run();

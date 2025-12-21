<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipOneHasManyRemoveAllTest extends DataTestCase
{

	public function testRemoveAllItems(): void
	{
		$author = new Author();
		$author->name = 'Stephen King';

		$tagFollower = new TagFollower();
		$tagFollower->author = $author;
		$tagFollower->tag = new Tag('Horror');

		$this->orm->authors->persistAndFlush($author);

		Assert::same(1, $author->tagFollowers->count());
		$author->tagFollowers->removeAll();
		Assert::same(0, $author->tagFollowers->count());

		$this->orm->authors->persistAndFlush($author);

		Assert::same(0, $author->tagFollowers->count());
		Assert::same(0, $author->tagFollowers->countStored());
	}
}


$test = new RelationshipOneHasManyRemoveAllTest();
$test->run();

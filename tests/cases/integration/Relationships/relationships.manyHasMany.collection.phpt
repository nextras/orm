<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\ManyHasMany;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsManyHasManyCollectionTest extends DataTestCase
{
	/** @var Book */
	private $book;

	/** @var ManyHasMany|Tag[] */
	private $tags;


	protected function setUp()
	{
		parent::setUp();

		$this->orm->clearIdentityMapAndCaches(IModel::I_KNOW_WHAT_I_AM_DOING);
		$this->book = $this->orm->books->getById(1);
		$this->tags = $this->book->tags;
	}


	public function testRemoveA()
	{
		$queries = $this->getQueries(function () {
			Assert::count(0, $this->tags->getEntitiesForPersistence());

			$tag2 = $this->orm->tags->getById(2); // SELECT

			// 5 SELECTS: all relationships (tag_followers, books_x_tags, tags (???), authors)
			// TRANSATION BEGIN
			// 4 DELETES: 2 books_x_tags, tag_follower, tag
			$this->orm->tags->remove($tag2);
			Assert::false($this->tags->isModified());
		});

		if ($queries) {
			Assert::count(11, $queries);
		}
	}
}


$test = new RelationshipsManyHasManyCollectionTest($dic);
$test->run();

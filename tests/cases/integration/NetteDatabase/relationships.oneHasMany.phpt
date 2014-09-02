<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class RelationshipOneHasManyTest extends DatabaseTestCase
{

	public function testBasics()
	{
		$author = $this->orm->authors->getById(1);

		$collection = $author->books->get()->findBy(['title!' => 'Book 1'])->orderBy('id');
		Assert::equal(1, $collection->count());
		Assert::equal('Book 2', $collection->fetch()->title);

		$collection = $author->books->get()->findBy(['title!' => 'Book 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal('Book 1', $collection->fetch()->title);
		Assert::equal('Book 2', $collection->fetch()->title);
	}


	public function testRemove()
	{
		/** @var Author $author */
		$author = $this->orm->authors->getById(1);

		$book = $this->orm->books->getById(1);

		$author->books->remove($book);
		$this->orm->authors->persistAndFlush($author);

		Assert::count(1, $author->books);
	}

}


$test = new RelationshipOneHasManyTest($dic);
$test->run();


<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nette\Database\ResultSet;
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

		$collection = $author->books->get()->findBy(['title!' => 'Book 1']);
		Assert::equal(1, $collection->count());
		Assert::equal('Book 2', $collection->fetch()->title);

		$collection = $author->books->get()->findBy(['title!' => 'Book 3']);
		Assert::equal(2, $collection->count());
		Assert::equal('Book 2', $collection->fetch()->title);
		Assert::equal('Book 1', $collection->fetch()->title);
	}


	public function testRemove()
	{
		/** @var Author $author */
		$author = $this->orm->authors->getById(2);

		$book = $this->orm->books->getById(3);

		$author->translatedBooks->remove($book);
		$this->orm->authors->persistAndFlush($author);

		Assert::count(1, $author->translatedBooks);
	}

}


$test = new RelationshipOneHasManyTest($dic);
$test->run();


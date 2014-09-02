<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';
Environment::lock('integration', TEMP_DIR);


/**
 * @testCase
 */
class RelationshipManyHasManyTest extends DatabaseTestCase
{

	public function testCache()
	{
		$book = $this->orm->books->getById(1);

		$collection = $book->tags->get()->findBy(['name!' => 'Tag 1'])->orderBy('id');
		Assert::equal(1, $collection->count());
		Assert::equal('Tag 2', $collection->fetch()->name);

		$collection = $book->tags->get()->findBy(['name!' => 'Tag 3'])->orderBy('id');
		Assert::equal(2, $collection->count());
		Assert::equal('Tag 1', $collection->fetch()->name);
		Assert::equal('Tag 2', $collection->fetch()->name);
	}

}


$test = new RelationshipManyHasManyTest($dic);
$test->run();


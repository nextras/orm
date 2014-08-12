<?php

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

}


$test = new RelationshipOneHasManyTest($dic);
$test->run();


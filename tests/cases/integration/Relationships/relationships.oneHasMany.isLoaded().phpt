<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyIsLoadedTest extends DataTestCase
{

	public function testIsLoaded()
	{
		$author1 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author1]);
		$author2 = $this->e('NextrasTests\Orm\Author');
		$this->e('NextrasTests\Orm\Book', ['author' => $author2]);
		$this->orm->authors->persist($author1);
		$this->orm->authors->persist($author2);
		$this->orm->authors->flush();

		foreach ($this->orm->authors->findAll() as $author) {
			Assert::false($author->books->isLoaded());
		}

		foreach ($this->orm->authors->findAll() as $author) {
			foreach ($author->books as $book) {}
			Assert::true($author->books->isLoaded());
		}
	}

}


$test = new RelationshipsOneHasManyIsLoadedTest($dic);
$test->run();

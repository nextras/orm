<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integrations;

use Mockery;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityRemoveTest extends TestCase
{

	public function testRemove()
	{
		$book = $this->e('NextrasTests\Orm\Book');
		$this->orm->books->persistAndFlush($book);

		$book = $this->orm->books->getById(1);
		Assert::same(1, $book->id);

		$this->orm->books->removeAndFlush($book);
		Assert::null($this->orm->books->findAll()->fetch());
	}

}


$test = new EntityRemoveTest($dic);
$test->run();


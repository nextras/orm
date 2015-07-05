<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositorySTITest extends DataTestCase
{

	public function testSelect()
	{
		$thread = $this->orm->contents->findBy(['NextrasTests\Orm\Thread->id' => 1])->fetch();
		Assert::type('NextrasTests\Orm\Thread', $thread);
	}


	public function testRead()
	{
		$all = $this->orm->contents->findAll()->orderBy('id');

		$thread = $all->fetch();
		Assert::type('NextrasTests\Orm\Thread', $thread);

		$comment = $all->fetch();
		Assert::type('NextrasTests\Orm\Comment', $comment);
	}

}


$test = new RepositorySTITest($dic);
$test->run();

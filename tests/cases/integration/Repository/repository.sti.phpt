<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use NextrasTests\Orm\Comment;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Thread;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositorySTITest extends DataTestCase
{

	public function testSelect()
	{
		$thread = $this->orm->contents->findBy(['NextrasTests\Orm\Thread->id' => 1])->fetch();
		Assert::type(Thread::class, $thread);
	}


	public function testRead()
	{
		$all = $this->orm->contents->findAll()->orderBy('id');

		$thread = $all->fetch();
		Assert::type(Thread::class, $thread);

		$comment = $all->fetch();
		Assert::type(Comment::class, $comment);
	}

}


$test = new RepositorySTITest($dic);
$test->run();

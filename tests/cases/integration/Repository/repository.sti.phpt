<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Dbal\Utils\DateTimeImmutable;
use NextrasTests\Orm\Comment;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Thread;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositorySTITest extends DataTestCase
{
	public function testSelect()
	{
		$thread = $this->orm->contents->findBy(['id' => 1])->fetch();
		Assert::type(Thread::class, $thread);

		foreach ($thread->comments->toCollection() as $comment) {
			Assert::type(Comment::class, $comment);
		}
	}


	public function testRead()
	{
		$all = $this->orm->contents->findAll()->orderBy('id');

		$thread = $all->fetch();
		Assert::type(Thread::class, $thread);

		$comment = $all->fetch();
		Assert::type(Comment::class, $comment);
	}


	public function testFindByFiltering()
	{
		$result = $this->orm->contents->findBy([
			'type' => 'comment',
			'NextrasTests\Orm\Comment::repliedAt>' => new DateTimeImmutable('2020-01-01 18:00:00'),
		]);
		Assert::same(1, $result->count());
		Assert::same(1, $result->countStored());
		Assert::type(Comment::class, $result->fetch());
	}
}


$test = new RepositorySTITest($dic);
$test->run();

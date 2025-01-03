<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Dbal\Utils\DateTimeImmutable;
use NextrasTests\Orm\Admin;
use NextrasTests\Orm\Comment;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Thread;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositorySTITest extends DataTestCase
{
	public function testSelect(): void
	{
		$thread = $this->orm->contents->getByIdChecked(1);
		Assert::type(Thread::class, $thread);

		foreach ($thread->comments->toCollection() as $comment) {
			Assert::type(Comment::class, $comment);
		}
	}


	public function testRead(): void
	{
		$all = $this->orm->contents->findAll()->orderBy('id');

		$thread = $all->fetch();
		Assert::type(Thread::class, $thread);

		$comment = $all->fetch();
		Assert::type(Comment::class, $comment);
	}


	public function testFindByFiltering(): void
	{
		$result = $this->orm->contents->findBy([
			'type' => 'comment',
			'NextrasTests\Orm\Comment::repliedAt>' => new DateTimeImmutable('2020-01-01 18:00:00'),
		]);
		Assert::same(1, $result->count());
		Assert::same(1, $result->countStored());
		Assert::type(Comment::class, $result->fetch());
	}


	public function testBreak(): void
	{
		$admin = $this->orm->admins->getById(1);
		Assert::type(Admin::class, $admin);
		Assert::same('John', $admin->personalData->firstName);
		Assert::same('Doe', $admin->personalData->lastName);
	}


}


$test = new RepositorySTITest();
$test->run();

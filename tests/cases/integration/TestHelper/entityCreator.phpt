<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\TestHelper;


use DateTimeImmutable;
use Nextras\Orm\TestHelper\EntityCreator;
use NextrasTests\Orm\Comment;
use NextrasTests\Orm\TestCase;
use NextrasTests\Orm\Thread;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityCreatorTest extends TestCase
{
	/**
	 * A non-nullable property backed by a value wrapper (here \DateTimeImmutable) must be created with the value
	 * passed in params, without the wrapper being prematurely initialized with a null and throwing NullValueException.
	 * @see https://github.com/nextras/orm/pull/811
	 */
	public function testNonNullableWrappedProperty(): void
	{
		$creator = $this->container->getByType(EntityCreator::class);

		$repliedAt = new DateTimeImmutable('2020-01-01 18:00:00');
		$comment = $creator->create(Comment::class, [
			'thread' => new Thread(),
			'repliedAt' => $repliedAt,
		]);

		Assert::type(Comment::class, $comment);
		Assert::equal($repliedAt, $comment->repliedAt);
	}
}


$test = new EntityCreatorTest();
$test->run();

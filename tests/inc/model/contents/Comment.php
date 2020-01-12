<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

/**
 * @property-read string             $type   {default comment}
 * @property      Thread|null        $thread {m:1 Thread::$comments}
 * @property      \DateTimeImmutable $repliedAt
 */
class Comment extends ThreadCommentCommon
{
}

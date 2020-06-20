<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntitySetReadOnlyValueTest extends DataTestCase
{
	public function testWithIPropertyWrapper(): void
	{
		$tagA = $this->orm->tags->getByIdChecked(1);
		$tagB = $this->orm->tags->getByIdChecked(2);

		$follower = new TagFollower();
		$follower->tag = $tagA;

		$follower->getMetadata()->getProperty('tag')->isReadonly = true;

		$follower->setReadOnlyValue('tag', $tagB);

		Assert::same($tagB, $follower->tag);
	}
}


$test = new EntitySetReadOnlyValueTest($dic);
$test->run();

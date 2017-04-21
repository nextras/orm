<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\TagFollower;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntitySetReadOnlyValueTest extends DataTestCase
{

	public function testWithIPropertyContainer()
	{
		$tagA = $this->orm->tags->getById(1);
		$tagB = $this->orm->tags->getById(2);

		$follower = new TagFollower();
		$follower->tag = $tagA;

		$follower->getMetadata()->getProperty('tag')->isReadonly = true;

		$follower->setReadOnlyValue('tag', $tagB);

		Assert::same($tagB, $follower->tag);
	}

}


$test = new EntitySetReadOnlyValueTest($dic);
$test->run();

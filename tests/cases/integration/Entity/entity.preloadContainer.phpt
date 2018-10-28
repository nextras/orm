<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;

use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityPreloadContainerTest extends DataTestCase
{
	public function testCombination()
	{
		foreach ($this->orm->books->findAll() as $book) {
			Assert::true($book->getPreloadContainer() !== null);
		}

		Assert::null($this->orm->books->getById(1)->getPreloadContainer());

		foreach ($this->orm->books->findAll() as $book) {
			Assert::true($book->getPreloadContainer() !== null);
		}
	}
}


$test = new EntityPreloadContainerTest($dic);
$test->run();


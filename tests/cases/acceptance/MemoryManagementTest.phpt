<?php

namespace NextrasTests\Orm\Collection;

use Mockery;
use Nextras\Orm\Model\IModel;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../bootstrap.php';


/**
 * @testCase
 * @dataProvider ../../sections.ini
 */
class MemoryManagementTest extends TestCase
{

	private function persistEntity()
	{
		$entity = new Author();
		$entity->name = 'Foobar';
		$this->orm->authors->persistAndFlush($entity);
	}


	public function testMemoryLeak()
	{
		$this->persistEntity();
		$this->orm->clearIdentityMapAndCaches(IModel::I_KNOW_WHAT_I_AM_DOING);

		$baseline = memory_get_usage(FALSE);

		for ($i = 0; $i < 200; ++$i) {
			$this->persistEntity();
			$this->orm->clearIdentityMapAndCaches(IModel::I_KNOW_WHAT_I_AM_DOING);

			if (memory_get_usage(FALSE) > $baseline * 1.05) {
				Assert::fail("Memory leak detected");
			}
		}

		Assert::true((bool) 'no leak detected');
	}

}


$test = new MemoryManagementTest($dic);
$test->run();

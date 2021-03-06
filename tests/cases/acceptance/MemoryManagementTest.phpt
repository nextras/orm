<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../databases.ini
 */

namespace NextrasTests\Orm\Collection;


use NextrasTests\Orm\Author;
use NextrasTests\Orm\TestCase;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../bootstrap.php';


class MemoryManagementTest extends TestCase
{
	private function persistEntity(): void
	{
		$entity = new Author();
		$entity->name = 'Foobar';
		$this->orm->authors->persistAndFlush($entity);
	}


	public function testMemoryLeak(): void
	{
		if (defined('PHPDBG_VERSION') || ini_get('pcov.enabled') === '1') {
			Environment::skip('Memory leaks are not tested during PHPDBG or PCOV coverage run.');
		}

		$this->persistEntity();
		$this->orm->clear();

		$baseline = memory_get_usage(false);

		for ($i = 0; $i < 200; ++$i) {
			$this->persistEntity();
			$this->orm->clear();
			gc_collect_cycles();

			$ratio = memory_get_usage(false) / $baseline;
			if ($ratio > 1.2) {
				Assert::fail("Memory leak detected with ration $ratio");
			}
		}

		Assert::true((bool) 'no leak detected');
	}
}


$test = new MemoryManagementTest();
$test->run();

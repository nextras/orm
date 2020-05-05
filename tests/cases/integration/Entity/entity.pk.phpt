<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use DateTimeImmutable;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Log;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityPkTest extends DataTestCase
{
	public function testDateTimeWithProxyPk()
	{
		$log = new Log();
		$log->id = $datetime = new DateTimeImmutable('tomorrow');
		$log->count = 3;
		$this->orm->persistAndFlush($log);

		$log->count = 5;
		$this->orm->persistAndFlush($log);

		$entry = $this->orm->logs->getById($datetime);
		Assert::true($entry !== null);
	}
}


$test = new EntityPkTest($dic);
$test->run();

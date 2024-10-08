<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use DateTimeImmutable;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Log;
use NextrasTests\Orm\TimeSeries;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityPkTest extends DataTestCase
{
	public function testDateTimeWithProxyPk(): void
	{
		$log = new Log();
		$log->id = $datetime = new DateTimeImmutable('2022-03-06T03:03:03Z');
		$log->count = 3;
		$this->orm->persistAndFlush($log);

		$log->count = 5;
		$this->orm->persistAndFlush($log);

		$entry = $this->orm->logs->getById($datetime);
		Assert::true($entry !== null);
	}

	public function testDateTimeWithProxyPkUpdate(): void
	{
		$timeSeries = new TimeSeries();
		$timeSeries->id = $datetime = new DateTimeImmutable('2022-03-06T03:03:03Z');
		$timeSeries->value = 3;
		$this->orm->persistAndFlush($timeSeries);

		$this->orm->clear();
		$timeSeries = $this->orm->timeSeries->getByIdChecked($datetime);
		$timeSeries->value = 5;
		$this->orm->persistAndFlush($timeSeries);

		$entry = $this->orm->timeSeries->getById($datetime);
		Assert::true($entry !== null);
	}
}


$test = new EntityPkTest();
$test->run();

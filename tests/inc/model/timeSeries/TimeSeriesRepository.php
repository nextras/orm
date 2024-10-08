<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Log>
 */
final class TimeSeriesRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [TimeSeries::class];
	}
}

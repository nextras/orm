<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @phpstan-extends Repository<Currency>
 */
class CurrenciesRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Currency::class];
	}
}

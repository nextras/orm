<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use MabeEnum\Enum;


class Currency extends Enum
{
	const CZK = 'CZK';
	const EUR = 'EUR';
	const GBP = 'GBP';
	const USD = 'USD';
}

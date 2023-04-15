<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


enum Currency: string
{
	case CZK = 'CZK';
	case EUR = 'EUR';
	case GBP = 'GBP';
	case USD = 'USD';
}

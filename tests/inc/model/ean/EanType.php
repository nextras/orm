<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use MabeEnum\Enum;


class EanType extends Enum
{
	const EAN13 = 1;
	const EAN8 = 2;
	const CODE39 = 3;
}

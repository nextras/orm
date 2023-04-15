<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


enum EanType: int
{
	case EAN13 = 1;
	case EAN8 = 2;
	case CODE39 = 3;
}

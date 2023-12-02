<?php

namespace NextrasTests\Orm;


enum FuelType: string
{
	case DIESEL = 'diesel';
	case PETROL = 'petrol';
	case ELECTRIC = 'electric';
	case HYBRID = 'hybrid';
}

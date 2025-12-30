<?php declare(strict_types = 1);

namespace Nextras\Orm\Repository;


enum PersistenceMode
{
	case Persist;
	case Remove;
}

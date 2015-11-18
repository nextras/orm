<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property      int                $id                {primary}
 * @property      int                $a
 * @property      int                $b
 * @property-read NULL|int           $computedProperty  updated via trigger
 */
final class Doughnut extends Entity
{

	/**
	 * @param int $a
	 * @param int $b
	 */
	public function __construct($a, $b)
	{
		parent::__construct();
		$this->a = $a;
		$this->b = $b;
	}

}

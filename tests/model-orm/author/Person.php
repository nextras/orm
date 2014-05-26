<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Model;

use DateTime;
use Nextras\Orm\Entity\Entity;


/**
 * @property string $name
 * @property DateTime|NULL $born
 * @property-read string $upperName {virtual}
 * @property-read string $fullName {virtual}
 */
abstract class Person extends Entity
{

	protected function getUpperName()
	{
		return strtoupper($this->name);
	}


	protected function getFullName()
	{
		return 'Full name: ' . $this->upperName;
	}

}

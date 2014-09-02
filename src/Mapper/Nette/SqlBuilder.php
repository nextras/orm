<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Nette;

use Nette\Database\Table\SqlBuilder as NetteSqlBuilder;


class SqlBuilder extends NetteSqlBuilder
{

	public function setOrder(array $columns, array $parameters)
	{
		$this->order = $columns;
		$this->parameters['order'] = $parameters;
	}

}

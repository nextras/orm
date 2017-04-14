<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;


interface IPersistAutoupdateMapper
{
	/**
	 * Returns reselecting columns as expandable expression for Dbal's %ex modifier.
	 */
	public function getAutoupdateReselectExpression(): array;
}

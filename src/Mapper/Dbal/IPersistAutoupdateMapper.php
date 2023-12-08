<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


interface IPersistAutoupdateMapper
{
	/**
	 * Returns re-selecting columns as expandable expression for Dbal's %ex modifier.
	 * @return list<mixed>
	 */
	public function getAutoupdateReselectExpression(): array;
}

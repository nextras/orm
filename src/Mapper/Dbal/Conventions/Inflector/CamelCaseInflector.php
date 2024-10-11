<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions\Inflector;


class CamelCaseInflector implements IInflector
{
	public function formatAsColumn(string $property): string
	{
		return $property;
	}


	public function formatAsProperty(string $column): string
	{
		return $column;
	}


	public function formatAsRelationshipProperty(string $column): string
	{
		if (str_ends_with($column, 'Id')) {
			$column = substr($column, 0, -2);
		}
		return $this->formatAsProperty($column);
	}
}

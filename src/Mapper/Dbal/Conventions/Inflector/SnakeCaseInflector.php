<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions\Inflector;


use Nextras\Orm\StorageReflection\StringHelper;


class SnakeCaseInflector implements IInflector
{
	public function formatAsColumn(string $property): string
	{
		return StringHelper::underscore($property);
	}


	public function formatAsProperty(string $column): string
	{
		return StringHelper::camelize($column);
	}


	public function formatAsRelationshipProperty(string $column): string
	{
		if (str_ends_with($column, '_id')) {
			$column = substr($column, 0, -3);
		}
		return $this->formatAsProperty($column);
	}
}

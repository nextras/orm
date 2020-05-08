<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions\Inflector;


/**
 * Inflector formats column<->properties.
 */
interface IInflector
{
	/**
	 * Formats entity property as a database column.
	 */
	public function formatAsColumn(string $property): string;


	/**
	 * Formats database column as entity property.
	 */
	public function formatAsProperty(string $column): string;


	/**
	 * Formats database column with foreign key as entity property.
	 * This method should behave like {@see IInflector::formatAsColumn()} with additional foregin key suffix strip.
	 * E.g. removing _id column suffix.
	 */
	public function formatAsRelationshipProperty(string $column): string;
}

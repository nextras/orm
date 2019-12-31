<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\Conventions\Inflector;


interface IInflector
{
	public function formatStorageKey(string $key): string;

	public function formatEntityKey(string $key): string;

	public function formatEntityForeignKey(string $key): string;
}

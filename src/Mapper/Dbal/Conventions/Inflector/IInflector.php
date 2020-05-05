<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions\Inflector;


interface IInflector
{
	public function formatStorageKey(string $key): string;

	public function formatEntityKey(string $key): string;

	public function formatEntityForeignKey(string $key): string;
}

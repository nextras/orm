<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\InvalidStateException;
use Tester\Environment;
use Tester\TestCase;


class Helper
{
	const SECTION_MSSQL = 'mssql';
	const SECTION_MYSQL = 'mysql';
	const SECTION_PGSQL = 'pgsql';
	const SECTION_ARRAY = 'array';


	public static function check(): void
	{
		if (!is_file(__DIR__ . '/../sections.ini')) {
			throw new InvalidStateException("Missing 'tests/sections.ini' configuration file.");
		}
		if (!is_file(__DIR__ . '/../php.ini')) {
			throw new InvalidStateException("Missing 'tests/php.ini' configuration file.");
		}
	}


	public static function getSection(): string
	{
		if (self::isRunByRunner()) {
			if (self::isRunForListingMethods()) {
				return self::SECTION_ARRAY;
			}

			$tmp = preg_filter('#--dataprovider=(.*)#Ai', '$1', $_SERVER['argv']);
			[$query] = explode('|', (string) reset($tmp), 2);
			return $query ?: self::SECTION_ARRAY;

		} else {
			$sections = parse_ini_file(__DIR__ . '/../sections.ini', true) ?: [];
			$sections = array_keys($sections);
			return $sections[0] ?? 'undefined';
		}
	}


	public static function isRunByRunner(): bool
	{
		return getenv(Environment::RUNNER) === '1';
	}


	public static function isRunForListingMethods(): bool
	{
		foreach ((array) $_SERVER['argv'] as $arg) {
			if ($arg === '--method=' . TestCase::LIST_METHODS) {
				return true;
			}
		}
		return false;
	}

}

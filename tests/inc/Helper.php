<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Tester\Environment;


class Helper
{
	public const SECTION_MSSQL = 'mssql';
	public const SECTION_MYSQL = 'mysql';
	public const SECTION_PGSQL = 'pgsql';
	public const SECTION_ARRAY = 'array';


	/**
	 * @param array<int|string, mixed> $config
	 */
	public static function getSection(array $config): string|null
	{
		static $driversMap = [
			'sqlsrv' => self::SECTION_MSSQL,
			'pgsql' => self::SECTION_PGSQL,
			'mysqli' => self::SECTION_MYSQL,
		];

		return $driversMap[$config['driver'] ?? ''] ?? self::SECTION_ARRAY;
	}


	public static function isRunByRunner(): bool
	{
		return getenv(Environment::VariableRunner) === '1';
	}
}

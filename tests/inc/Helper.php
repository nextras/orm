<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Tester\Environment;


class Helper
{
	const SECTION_MSSQL = 'mssql';
	const SECTION_MYSQL = 'mysql';
	const SECTION_PGSQL = 'pgsql';
	const SECTION_ARRAY = 'array';


	/**
	 * @param array<mixed, mixed> $config
	 */
	public static function getSection(array $config): ?string
	{
		static $driversMap = [
			'sqlsrv' => self::SECTION_MSSQL,
			'pgsql' => self::SECTION_PGSQL,
			'mysqli' => self::SECTION_MYSQL,
		];

		return $driversMap[$config['driver'] ?? null] ?? self::SECTION_ARRAY;
	}


	public static function isRunByRunner(): bool
	{
		return getenv(Environment::VariableRunner) === '1';
	}
}

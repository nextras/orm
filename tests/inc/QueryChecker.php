<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nette\Utils\FileSystem;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Result\Result;
use Tester\Assert;


class QueryChecker implements ILogger
{
	/** @var string */
	private $name;

	/** @var string */
	private $sqls = '';


	public function __construct(string $name)
	{
		$this->name = str_replace('\\', '/', $name);
	}


	public function assert(): void
	{
		$file = __DIR__ . '/../sqls/' . $this->name . '.sql';
		$ci = getenv('GITHUB_ACTIONS') !== false;
		if (!$ci) {
			FileSystem::createDir(dirname($file));
			FileSystem::write($file, $this->sqls);
		} else {
			if (!file_exists($file)) {
				throw new \Exception("Missing $this->name.sql file, run `composer tests` locally (with Postgres) to generate the expected SQL queries files.");
			}
			Assert::same(FileSystem::read($file), $this->sqls);
		}
	}


	public function onConnect(): void
	{
	}


	public function onDisconnect(): void
	{
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result): void
	{
		if (strpos($sqlQuery, 'pg_catalog.') !== false) return;
		$this->sqls .= "$sqlQuery;\n";
	}


	public function onQueryException(string $sqlQuery, float $timeTaken, ?DriverException $exception): void
	{
		if (strpos($sqlQuery, 'pg_catalog.') !== false) return;
		$this->sqls .= "$sqlQuery;\n";
	}
}

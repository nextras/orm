<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
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

	private SqlFormatter $formatter;


	public function __construct(string $name)
	{
		$this->name = str_replace('\\', '/', $name);
		$this->formatter = new SqlFormatter(new NullHighlighter());
	}


	public function assert(): void
	{
		$file = __DIR__ . '/../sqls/' . $this->name . '.sql';
		$ci = getenv('GITHUB_ACTIONS') !== false;
		if (!$ci) {
			FileSystem::createDir(dirname($file));
			FileSystem::write($file, trim($this->sqls) . "\n");
		} else {
			if (!file_exists($file)) {
				throw new \Exception("Missing $this->name.sql file, run `composer tests` locally (with Postgres) to generate the expected SQL queries files.");
			}
			Assert::same(FileSystem::read($file), trim($this->sqls) . "\n");
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
		if (str_contains($sqlQuery, 'pg_catalog.')) return;
		$formattedSql = str_contains($sqlQuery, 'LEFT JOIN') ? $this->formatter->format($sqlQuery) . ";\n\n" : $sqlQuery . ";\n";
		$this->sqls .= $formattedSql;
	}


	public function onQueryException(string $sqlQuery, float $timeTaken, ?DriverException $exception): void
	{
		if (str_contains($sqlQuery, 'pg_catalog.')) return;
		$formattedSql = str_contains($sqlQuery, 'LEFT JOIN') ? $this->formatter->format($sqlQuery) . ";\n\n" : $sqlQuery . ";\n";
		$this->sqls .= $formattedSql;
	}
}

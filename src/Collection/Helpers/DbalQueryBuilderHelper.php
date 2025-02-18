<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nette\Utils\Json;
use Nette\Utils\Strings;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Collection\Functions\FetchPropertyFunction;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Repository\IRepository;
use function array_shift;
use function array_unshift;
use function count;
use function implode;
use function md5;
use function reset;


/**
 * QueryBuilder helper for Nextras Dbal.
 */
class DbalQueryBuilderHelper
{
	/**
	 * Returns suitable table alias, strips db/schema name and prepends expression $tokens as part of the table name.
	 * @param array<int, string> $tokens
	 */
	public static function getAlias(string|Fqn $name, array $tokens = []): string
	{
		$name = $name instanceof Fqn ? $name->name : $name;
		$name = Strings::replace($name, '#[^a-z0-9_]#i', replacement: '');
		if (count($tokens) === 0) {
			return $name;
		} else {
			return implode('_', $tokens) . '_' . $name;
		}
	}


	private string $platformName;


	/**
	 * @param IRepository<IEntity> $repository
	 * @phpstan-param IRepository<*> $repository
	 */
	public function __construct(
		private readonly IRepository $repository,
	)
	{
		$mapper = $this->repository->getMapper();
		if (!$mapper instanceof DbalMapper) throw new InvalidArgumentException("");
		$this->platformName = $mapper->getDatabasePlatform()->getName();
	}


	/**
	 * Processes an array expression when the first argument at 0 is a collection function name
	 * and the rest are function argument. If the function name is not present, an implicit
	 * {@link ConjunctionOperatorFunction} is used.
	 *
	 * @param array<string, mixed>|array<int|string, mixed>|list<mixed>|string $expression
	 * @param Aggregator<mixed>|null $aggregator
	 */
	public function processExpression(
		QueryBuilder $builder,
		array|string $expression,
		?Aggregator $aggregator,
	): DbalExpressionResult
	{
		if (is_string($expression)) {
			$function = FetchPropertyFunction::class;
			$expression = [$expression];
		} else {
			$function = isset($expression[0]) ? array_shift($expression) : ICollection::AND;
		}

		$collectionFunction = $this->repository->getCollectionFunction($function);
		return $collectionFunction->processDbalExpression($this, $builder, $expression, $aggregator);
	}


	/**
	 * @return list<mixed>
	 */
	public function processOrderDirection(DbalExpressionResult $expression, string $direction): array
	{
		if ($expression->expression !== null) {
			$args = $expression->getArgsForExpansion();
		} else {
			$args = $expression->getHavingArgsForExpansion();
		}
		if ($this->platformName === 'mysql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex IS NULL, %ex ASC', $args, $args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex IS NOT NULL, %ex DESC', $args, $args];
			}
		} elseif ($this->platformName === 'mssql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['CASE WHEN %ex IS NULL THEN 1 ELSE 0 END, %ex ASC', $args, $args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['CASE WHEN %ex IS NOT NULL THEN 1 ELSE 0 END, %ex DESC', $args, $args];
			}
		} elseif ($this->platformName === 'pgsql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex ASC', $args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex DESC', $args];
			} elseif ($direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC NULLS FIRST', $args];
			} elseif ($direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC NULLS LAST', $args];
			}
		}

		throw new NotSupportedException();
	}


	/**
	 * @param literal-string $dbalModifier
	 * @param list<DbalTableJoin> $joins
	 * @return list<DbalTableJoin>
	 */
	public function mergeJoins(string $dbalModifier, array $joins): array
	{
		if (count($joins) === 0) return [];

		/** @var array<array<DbalTableJoin>> $aggregated */
		$aggregated = [];
		foreach ($joins as $join) {
			$hash = md5(Json::encode([$join->onExpression, $join->onArgs]));
			/**
			 * We aggregate only by alias as we assume that having a different alias
			 * for different select-from expressions is a responsibility of the query-helper/user.
			 */
			$aggregated[$join->toAlias][$hash] = $join;
		}

		$merged = [];
		foreach ($aggregated as $sameJoins) {
			$first = reset($sameJoins);
			if (count($sameJoins) === 1) {
				$merged[] = $first;
			} else {
				$args = [];
				foreach ($sameJoins as $sameJoin) {
					$joinArgs = $sameJoin->onArgs;
					array_unshift($joinArgs, $sameJoin->onExpression);
					$args[] = $joinArgs;
				}
				$merged[] = new DbalTableJoin(
					toExpression: $first->toExpression,
					toArgs: $first->toArgs,
					toAlias: $first->toAlias,
					onExpression: $dbalModifier,
					onArgs: [$args],
					toPrimaryKey: $first->toPrimaryKey,
				);
			}
		}

		return $merged;
	}
}

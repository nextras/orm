<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;


class DisjunctionOperatorFunction implements IArrayFunction, IQueryBuilderFunction
{
	/** @var ConditionParser */
	private $conditionParser;


	public function __construct(ConditionParser $conditionParserHelper)
	{
		$this->conditionParser = $conditionParserHelper;
	}


	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args)
	{
		foreach ($this->normalizeFunctions($args) as $arg) {
			$callback = $helper->createFilter($arg);
			if ($callback($entity) == true) { // intentionally ==
				return true;
			}
		}
		return false;
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): DbalExpressionResult
	{
		$isHavingClause = false;
		$processedArgs = [];
		foreach ($this->normalizeFunctions($args) as $collectionFunctionArgs) {
			$expression = $helper->processFilterFunction($builder, $collectionFunctionArgs);
			$processedArgs[] = $expression->args;
			$isHavingClause = $isHavingClause || $expression->isHavingClause;
		}
		return new DbalExpressionResult(['%or', $processedArgs], $isHavingClause);
	}


	/**
	 * Normalizes directly entered column => value expression to expression array.
	 * @phpstan-param array<string, mixed>|list<mixed> $args
	 * @phpstan-return list<mixed>
	 */
	protected function normalizeFunctions(array $args): array
	{
		// Args passed as array values
		// [ICollection::AND, ['id' => 1], ['name' => John]]
		if (isset($args[0])) {
			/** @phpstan-var list<mixed> $args */
			return $args;
		}

		// Args passed as keys
		// [ICollection::AND, 'id' => 1, 'name!=' => John]
		/** @phpstan-var array<string, mixed> $args */
		$processedArgs = [];
		foreach ($args as $argName => $argValue) {
			$functionCall = $this->conditionParser->parsePropertyOperator($argName);
			$functionCall[] = $argValue;
			$processedArgs[] = $functionCall;
		}
		return $processedArgs;
	}
}

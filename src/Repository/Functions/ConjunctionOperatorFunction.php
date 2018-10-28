<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository\Functions;

use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFilterFunction;
use Nextras\Orm\Mapper\Dbal\QueryBuilderHelper;
use Nextras\Orm\Mapper\Memory\CustomFunctions\IArrayFilterFunction;


class ConjunctionOperatorFunction implements IArrayFilterFunction, IQueryBuilderFilterFunction
{
	public function processArrayFilter(ArrayCollectionHelper $helper, IEntity $entity, array $args): bool
	{
		foreach ($this->normalizeFunctions($args) as $arg) {
			$callback = $helper->createFilter($arg);
			if (!$callback($entity)) {
				return false;
			}
		}
		return true;
	}


	public function processQueryBuilderFilter(QueryBuilderHelper $helper, QueryBuilder $builder, array $args): array
	{
		$processedArgs = [];
		foreach ($this->normalizeFunctions($args) as $arg) {
			$processedArgs[] = $helper->processFilterFunction($builder, $arg);
		}
		return ['%and', $processedArgs];
	}


	private function normalizeFunctions(array $args): array
	{
		if (isset($args[0])) {
			return $args;
		}

		$processedArgs = [];
		foreach ($args as $argName => $argValue) {
			[$argName, $operator] = ConditionParserHelper::parsePropertyOperator($argName);
			$processedArgs[] = [ValueOperatorFunction::class, $operator, $argName, $argValue];
		}
		return $processedArgs;
	}
}

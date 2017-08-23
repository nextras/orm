<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nette\Utils\Strings;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFilterFunction;
use Nextras\Orm\Mapper\Dbal\QueryBuilderHelper;
use Nextras\Orm\Mapper\Memory\CustomFunctions\IArrayFilterFunction;


final class LikeFilterFunction implements IArrayFilterFunction, IQueryBuilderFilterFunction
{
	public function processArrayFilter(ArrayCollectionHelper $helper, IEntity $entity, array $args): bool
	{
		assert(count($args) === 2 && is_string($args[0]) && is_string($args[1]));
		$value = $helper->getValue($entity, $args[0])->value;
		return Strings::startsWith($value, $args[1]);
	}


	public function processQueryBuilderFilter(QueryBuilderHelper $helper, QueryBuilder $builder, array $args): array
	{
		assert(count($args) === 2 && is_string($args[0]) && is_string($args[1]));
		$column = $helper->processPropertyExpr($builder, $args[0])->column;
		return ['%column LIKE %like_', $column, $args[1]];
	}
}

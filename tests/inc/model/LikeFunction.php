<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nette\Utils\Strings;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFunction;
use Nextras\Orm\Mapper\Dbal\QueryBuilderHelper;
use Nextras\Orm\Mapper\Memory\CustomFunctions\IArrayFunction;


final class LikeFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayFilter(ArrayCollectionHelper $helper, array $entities, array $args): array
	{
		assert(count($args) === 2 && is_string($args[0]) && is_string($args[1]));
		return array_filter($entities, function (IEntity $entity) use ($helper, $args) {
			return Strings::startsWith($helper->getValue($entity, $args[0])->value, $args[1]);
		});
	}


	public function processQueryBuilderFilter(QueryBuilderHelper $helper, QueryBuilder $builder, array $args): QueryBuilder
	{
		assert(count($args) === 2 && is_string($args[0]) && is_string($args[1]));
		$column = $helper->processPropertyExpr($builder, $args[0])->column;
		$builder->andWhere('%column LIKE %like_', $column, $args[1]);
		return $builder;
	}
}

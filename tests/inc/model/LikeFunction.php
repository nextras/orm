<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nette\Utils\Strings;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;


final class LikeFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args)
	{
		\assert(\count($args) === 2 && \is_string($args[0]) && \is_string($args[1]));

		$value = $helper->getValue($entity, $args[0])->value;
		return Strings::startsWith($value, $args[1]);
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): DbalExpressionResult
	{
		\assert(\count($args) === 2 && \is_string($args[0]) && \is_string($args[1]));

		$expression = $helper->processPropertyExpr($builder, $args[0]);
		return $expression->append('LIKE %like_', $args[1]);
	}
}

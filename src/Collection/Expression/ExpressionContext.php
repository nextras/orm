<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Expression;


/**
 * @internal
 *
 * Determines if the expression is processed for AND subtree, OR subtree or pure expression, e.g., a sorting expression.
 * OR subtree requires more complex SQL construction, in other modes we may optimize the resulting SQL.
 */
enum ExpressionContext
{
	case FilterAnd;
	case FilterOr;
	case ValueExpression;
}

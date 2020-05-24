<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Orm\Collection\Functions\CompareEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CompareLikeFunction;
use Nextras\Orm\Collection\Functions\CompareNotEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanEqualsFunction;
use Nextras\Orm\Collection\Functions\CompareSmallerThanFunction;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use function array_shift;
use function explode;
use function is_subclass_of;
use function preg_match;
use function strpos;
use function trigger_error;


class ConditionParserHelper
{
	/**
	 * @return array{class-string, string}
	 */
	public static function parsePropertyOperator(string $condition): array
	{
		if (!preg_match('#^(.+?)(!=|<=|>=|=|>|<|~)?$#', $condition, $matches)) {
			return [CompareEqualsFunction::class, $condition];
		}
		$operator = $matches[2] ?? '=';
		if ($operator === '=') {
			return [CompareEqualsFunction::class, $matches[1]];
		} elseif ($operator === '!=') {
			return [CompareNotEqualsFunction::class, $matches[1]];
		} elseif ($operator === '>=') {
			return [CompareGreaterThanEqualsFunction::class, $matches[1]];
		} elseif ($operator === '>') {
			return [CompareGreaterThanFunction::class, $matches[1]];
		} elseif ($operator === '<=') {
			return [CompareSmallerThanEqualsFunction::class, $matches[1]];
		} elseif ($operator === '<') {
			return [CompareSmallerThanFunction::class, $matches[1]];
		} elseif ($operator === '~') {
			return [CompareLikeFunction::class, $matches[1]];
		} else {
			throw new InvalidStateException();
		}
	}


	/**
	 * @return array{list<string>, class-string<IEntity>|null}
	 */
	public static function parsePropertyExpr(string $propertyPath): array
	{
		static $regexp = '#
			^
			(?:([\w\\\]+)::)?
			([\w\\\]++(?:->\w++)*+)
			$
		#x';

		if (!preg_match($regexp, $propertyPath, $matches)) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		array_shift($matches); // whole expression

		/** @var string $source */
		$source = array_shift($matches);
		$tokens = explode('->', array_shift($matches));

		if ($source === '') {
			$source = null;
			if ($tokens[0] === 'this') {
				trigger_error("Using 'this->' is deprecated; use property traversing directly without 'this->'.", E_USER_DEPRECATED);
				array_shift($tokens);
			} elseif (strpos($tokens[0], '\\') !== false) {
				$source = array_shift($tokens);
				trigger_error("Using STI class prefix '$source->' is deprecated; use with double-colon '$source::'.", E_USER_DEPRECATED);
			}
		}

		if ($source !== null && !is_subclass_of($source, IEntity::class)) {
			throw new InvalidArgumentException("Property expression '$propertyPath' uses class '$source' that is not " . IEntity::class . '.');
		}

		return [$tokens, $source];
	}
}

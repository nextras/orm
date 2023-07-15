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
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function array_shift;
use function explode;
use function is_subclass_of;
use function preg_match;
use function trigger_error;


/**
 * @internal
 */
class ConditionParser
{
	// language=PhpRegExp
	protected const PATH_REGEXP = '(?:([\w\\\]+)::)?([\w\\\]++(?:->\w++)*+)';


	/**
	 * @return array{class-string, string}
	 */
	public function parsePropertyOperator(string $condition): array
	{
		// language=PhpRegExp
		$regexp = '#^(?P<path>' . self::PATH_REGEXP . ')(?P<operator>!=|<=|>=|=|>|<|~)?$#';
		if (preg_match($regexp, $condition, $matches) !== 1) {
			return [CompareEqualsFunction::class, $condition];
		}
		$operator = $matches['operator'] ?? '=';
		$path = $matches['path'];

		if ($operator === '=') {
			return [CompareEqualsFunction::class, $path];
		} elseif ($operator === '!=') {
			return [CompareNotEqualsFunction::class, $path];
		} elseif ($operator === '>=') {
			return [CompareGreaterThanEqualsFunction::class, $path];
		} elseif ($operator === '>') {
			return [CompareGreaterThanFunction::class, $path];
		} elseif ($operator === '<=') {
			return [CompareSmallerThanEqualsFunction::class, $path];
		} elseif ($operator === '<') {
			return [CompareSmallerThanFunction::class, $path];
		} elseif ($operator === '~') {
			return [CompareLikeFunction::class, $path];
		} else {
			throw new InvalidStateException();
		}
	}


	/**
	 * @return array{list<string>, class-string<IEntity>|null}
	 */
	public function parsePropertyExpr(string $propertyPath): array
	{
		// language=PhpRegExp
		$regexp = '#^' . self::PATH_REGEXP . '$#';
		if (preg_match($regexp, $propertyPath, $matches) !== 1) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		array_shift($matches); // whole expression

		/** @var string $source */
		$source = array_shift($matches);
		assert(count($matches) > 0);
		$tokens = explode('->', array_shift($matches));

		if ($source === '') {
			$source = null;
			if ($tokens[0] === 'this') {
				trigger_error("Using 'this->' is deprecated; use property traversing directly without 'this->'.", E_USER_DEPRECATED);
				array_shift($tokens);
			} elseif (str_contains($tokens[0], '\\')) {
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

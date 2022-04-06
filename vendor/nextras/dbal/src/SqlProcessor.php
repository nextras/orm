<?php declare(strict_types = 1);

namespace Nextras\Dbal;


use DateInterval;
use DateTime;
use DateTimeImmutable;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Utils\StrictObjectTrait;
use SplObjectStorage;


class SqlProcessor
{
	use StrictObjectTrait;


	/**
	 * @var array (name => [supports ?, supports [], expected type])
	 * @phpstan-var array<string, array{bool, bool, string}>
	 */
	protected $modifiers = [
		// expressions
		's' => [true, true, 'string'],
		'json' => [true, true, 'pretty much anything'],
		'i' => [true, true, 'int'],
		'f' => [true, true, '(finite) float'],
		'b' => [true, true, 'bool'],
		'dt' => [true, true, 'DateTimeInterface'],
		'dts' => [true, true, 'DateTimeInterface'], // @deprecated use ldt
		'ldt' => [true, true, 'DateTimeInterface'],
		'di' => [true, true, 'DateInterval'],
		'blob' => [true, true, 'blob string'],
		'_like' => [true, false, 'string'],
		'like_' => [true, false, 'string'],
		'_like_' => [true, false, 'string'],
		'any' => [false, false, 'pretty much anything'],
		'and' => [false, false, 'array'],
		'or' => [false, false, 'array'],
		'multiOr' => [false, false, 'array'],

		// SQL constructs
		'table' => [false, true, 'string'],
		'column' => [false, true, 'string'],
		'values' => [false, true, 'array'],
		'set' => [false, false, 'array'],
		'raw' => [false, false, 'string'],
		'ex' => [false, false, 'array'],
	];

	/**
	 * @var array (modifier => callable)
	 * @phpstan-var array<string, callable(SqlProcessor, mixed, string): mixed>
	 */
	protected $customModifiers = [];

	/** @var SplObjectStorage<ISqlProcessorModifierResolver,mixed>|ISqlProcessorModifierResolver[] */
	protected $modifierResolvers;

	/**
	 * @var array
	 * @phpstan-var array<string, string>
	 */
	private $identifiers;

	/** @var IPlatform */
	private $platform;


	public function __construct(IPlatform $platform)
	{
		$this->platform = $platform;
		$this->modifierResolvers = new SplObjectStorage();
	}


	/**
	 * @param callable $callback (SqlProcessor, mixed $value, string $modifier): mixed
	 * @phpstan-param callable(SqlProcessor, mixed, string): mixed $callback
	 */
	public function setCustomModifier(string $modifier, callable $callback): void
	{
		$baseModifier = trim($modifier, '[]?');
		if (isset($this->modifiers[$baseModifier])) {
			throw new InvalidArgumentException("Cannot override core modifier '$baseModifier'.");
		}

		$this->customModifiers[$modifier] = $callback;
	}


	/**
	 * Adds a modifier resolver for any unspecified type (either implicit or explicit `%any` modifier).
	 */
	public function addModifierResolver(ISqlProcessorModifierResolver $resolver): void
	{
		$this->modifierResolvers->attach($resolver);
	}


	/**
	 * Removes modifier resolver.
	 */
	public function removeModifierResolver(ISqlProcessorModifierResolver $resolver): void
	{
		$this->modifierResolvers->detach($resolver);
	}


	/**
	 * @param mixed[] $args
	 */
	public function process(array $args): string
	{dump($)
		$last = count($args) - 1;
		$fragments = [];

		for ($i = 0, $j = 0; $j <= $last; $j++) {
			if (!is_string($args[$j])) {
				throw new InvalidArgumentException($j === 0
					? 'Query fragment must be string.'
					: "Redundant query parameter or missing modifier in query fragment '$args[$i]'."
				);
			}

			$i = $j;
			$fragments[] = preg_replace_callback(
				'#%((?:\.\.\.)?+\??+\w++(?:\[]){0,2}+)|(%%)|(\[\[)|(]])|\[(.+?)]#S', // %modifier | %% | %[ | %] | [identifier]
				function ($matches) use ($args, &$j, $last): string {
					if ($matches[1] !== '') {
						if ($j === $last) {
							throw new InvalidArgumentException("Missing query parameter for modifier $matches[0].");
						}
						return $this->processModifier($matches[1], $args[++$j]);

					} elseif ($matches[2] !== '') {
						return '%';

					} elseif ($matches[3] !== '') {
						return '[';

					} elseif ($matches[4] !== '') {
						return ']';

					} elseif (!ctype_digit($matches[5])) {
						return $this->identifierToSql($matches[5]);

					} else {
						return "[$matches[5]]";
					}
				},
				$args[$i]
			);

			if ($i === $j && $j !== $last) {
				throw new InvalidArgumentException("Redundant query parameter or missing modifier in query fragment '$args[$i]'.");
			}
		}

		return implode(' ', $fragments);
	}


	/**
	 * @param mixed $value
	 */
	public function processModifier(string $type, $value): string
	{
		if ($type === 'any') {
			$type = $this->detectType($value) ?? 'any';
		}

		switch (gettype($value)) {
			case 'string':
				switch ($type) {
					case 'any':
					case 's':
					case '?s':
						return $this->platform->formatString($value);

					case 'json':
					case '?json':
						return $this->platform->formatJson($value);

					case 'i':
					case '?i':
						if (preg_match('#^-?[1-9][0-9]*+\z#', $value) !== 1) {
							break;
						}
						return (string) $value;

					case '_like':
						return $this->platform->formatStringLike($value, -1);
					case 'like_':
						return $this->platform->formatStringLike($value, 1);
					case '_like_':
						return $this->platform->formatStringLike($value, 0);

					/** @noinspection PhpMissingBreakStatementInspection */
					case 'column':
						if ($value === '*') {
							return '*';
						}
					// intentional pass-through
					case 'table':
						return $this->identifierToSql($value);

					case 'blob':
						return $this->platform->formatBlob($value);

					case 'raw':
						return $value;
				}

				break;
			case 'integer':
				switch ($type) {
					case 'any':
					case 'i':
					case '?i':
						return (string) $value;

					case 'json':
					case '?json':
						return $this->platform->formatJson($value);
				}

				break;
			case 'double':
				if (is_finite($value)) { // database can not handle INF and NAN
					switch ($type) {
						case 'any':
						case 'f':
						case '?f':
							$tmp = json_encode($value);
							assert(is_string($tmp));
							return $tmp . (strpos($tmp, '.') === false ? '.0' : '');

						case 'json':
						case '?json':
							return $this->platform->formatJson($value);
					}
				}

				break;
			case 'boolean':
				switch ($type) {
					case 'any':
					case 'b':
					case '?b':
						return $this->platform->formatBool($value);

					case 'json':
					case '?json':
						return $this->platform->formatJson($value);
				}

				break;
			case 'NULL':
				switch ($type) {
					case 'any':
					case '?s':
					case '?i':
					case '?f':
					case '?b':
					case '?dt':
					case '?dts':
					case '?ldt':
					case '?di':
					case '?blob':
					case '?json':
						return 'NULL';
				}

				break;
			case 'object':
				if ($type === 'json' || $type === '?json') {
					return $this->platform->formatJson($value);
				}

				if ($value instanceof DateTimeImmutable || $value instanceof DateTime) {
					switch ($type) {
						case 'any':
						case 'dt':
						case '?dt':
							return $this->platform->formatDateTime($value);

						case 'dts':
						case '?dts':
						case 'ldt':
						case '?ldt':
							return $this->platform->formatLocalDateTime($value);
					}

				} elseif ($value instanceof DateInterval) {
					switch ($type) {
						case 'any':
						case 'di':
						case '?di':
							return $this->platform->formatDateInterval($value);
					}

				} elseif (method_exists($value, '__toString')) {
					switch ($type) {
						case 'any':
						case 's':
						case '?s':
							return $this->platform->formatString((string) $value);

						case '_like':
							return $this->platform->formatStringLike((string) $value, -1);
						case 'like_':
							return $this->platform->formatStringLike((string) $value, 1);
						case '_like_':
							return $this->platform->formatStringLike((string) $value, 0);
					}
				}

				break;
			case 'array':
				switch ($type) {
					// micro-optimizations
					case 'any':
						return $this->processArray("any[]", $value);

					case 'i[]':
						foreach ($value as $v) {
							if (!is_int($v)) break 2; // fallback to processArray
						}
						return '(' . implode(', ', $value) . ')';

					case 's[]':
						foreach ($value as &$subValue) {
							if (!is_string($subValue)) break 2; // fallback to processArray
							$subValue = $this->platform->formatString($subValue);
						}
						return '(' . implode(', ', $value) . ')';

					case 'json':
					case '?json':
						return $this->platform->formatJson($value);

					// normal
					case 'column[]':
					case '...column[]':
					case 'table[]':
					case '...table[]':
						$subType = substr($type, 0, -2);
						foreach ($value as &$subValue) {
							$subValue = $this->processModifier($subType, $subValue);
						}
						return implode(', ', $value);

					case 'and':
					case 'or':
						return $this->processWhere($type, $value);

					case 'multiOr':
						return $this->processMultiColumnOr($value);

					case 'values':
						return $this->processValues($value);

					case 'values[]':
						return $this->processMultiValues($value);

					case 'set':
						return $this->processSet($value);

					case 'ex':
						return $this->process($value);
				}

				if (substr($type, -1) === ']') {
					$baseType = trim(trim($type, '.'), '[]?');
					if (isset($this->modifiers[$baseType]) && $this->modifiers[$baseType][1]) {
						return $this->processArray($type, $value);
					}
				}
		}

		$baseType = trim(trim($type, '.'), '[]?');

		if (isset($this->customModifiers[$baseType])) {
			return $this->customModifiers[$baseType]($this, $value, $type);
		}

		$typeNullable = $type[0] === '?';
		$typeArray = substr($type, -2) === '[]';

		if (!isset($this->modifiers[$baseType])) {
			throw new InvalidArgumentException("Unknown modifier %$type.");

		} elseif (($typeNullable && !$this->modifiers[$baseType][0]) || ($typeArray && !$this->modifiers[$baseType][1])) {
			throw new InvalidArgumentException("Modifier %$baseType does not have %$type variant.");

		} elseif ($typeArray) {
			$this->throwInvalidValueTypeException($type, $value, 'array');

		} elseif ($value === null && !$typeNullable && $this->modifiers[$baseType][0]) {
			$this->throwWrongModifierException($type, $value, "?$type");

		} elseif (is_array($value) && $this->modifiers[$baseType][1]) {
			$this->throwWrongModifierException($type, $value, "{$type}[]");

		} else {
			$this->throwInvalidValueTypeException($type, $value, $this->modifiers[$baseType][2]);
		}
	}


	/**
	 * @param mixed $value
	 */
	protected function detectType($value): ?string
	{
		foreach ($this->modifierResolvers as $modifierResolver) {
			$resolved = $modifierResolver->resolve($value);
			if ($resolved !== null) return $resolved;
		}
		return null;
	}


	/**
	 * @param mixed $value
	 * @phpstan-return never
	 */
	protected function throwInvalidValueTypeException(string $type, $value, string $expectedType): void
	{
		$actualType = $this->getVariableTypeName($value);
		throw new InvalidArgumentException("Modifier %$type expects value to be $expectedType, $actualType given.");
	}


	/**
	 * @param mixed $value
	 * @phpstan-return never
	 */
	protected function throwWrongModifierException(string $type, $value, string $hint): void
	{
		$valueLabel = is_scalar($value) ? var_export($value, true) : gettype($value);
		throw new InvalidArgumentException("Modifier %$type does not allow $valueLabel value, use modifier %$hint instead.");
	}


	/**
	 * @phpstan-param array<mixed> $value
	 */
	protected function processArray(string $type, array $value): string
	{
		$subType = substr($type, 0, -2);
		$wrapped = true;

		if (strncmp($subType, '...', 3) === 0) {
			$subType = substr($subType, 3);
			$wrapped = false;
		}

		foreach ($value as &$subValue) {
			$subValue = $this->processModifier($subType, $subValue);
		}

		if ($wrapped) {
			return '(' . implode(', ', $value) . ')';
		} else {
			return implode(', ', $value);
		}
	}


	/**
	 * @phpstan-param array<string, mixed> $value
	 */
	protected function processSet(array $value): string
	{
		$values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$column = $this->identifierToSql($key[0]);
			$expr = $this->processModifier($key[1] ?? 'any', $val);
			$values[] = "$column = $expr";
		}

		return implode(', ', $values);
	}


	/**
	 * @phpstan-param array<string, mixed> $value
	 */
	protected function processMultiValues(array $value): string
	{
		if (count($value) === 0) {
			throw new InvalidArgumentException('Modifier %values[] must contain at least one array element.');
		}

		$keys = $values = [];
		foreach (array_keys(reset($value)) as $key) {
			$keys[] = $this->identifierToSql(explode('%', (string) $key, 2)[0]);
		}
		foreach ($value as $subValue) {
			if (!is_array($subValue) || count($subValue) === 0) {
				$values[] = '(' . str_repeat('DEFAULT, ', max(count($keys) - 1, 0)) . 'DEFAULT)';
			} else {
				$subValues = [];
				foreach ($subValue as $_key => $val) {
					$key = explode('%', (string) $_key, 2);
					$subValues[] = $this->processModifier($key[1] ?? 'any', $val);
				}
				$values[] = '(' . implode(', ', $subValues) . ')';
			}
		}

		return (count($keys) > 0 ? '(' . implode(', ', $keys) . ') ' : '') . 'VALUES ' . implode(', ', $values);
	}


	/**
	 * @phpstan-param array<string, mixed> $value
	 */
	private function processValues(array $value): string
	{
		if (count($value) === 0) {
			return 'VALUES (DEFAULT)';
		}

		$keys = $values = [];
		foreach ($value as $_key => $val) {
			$key = explode('%', $_key, 2);
			$keys[] = $this->identifierToSql($key[0]);
			$values[] = $this->processModifier($key[1] ?? 'any', $val);
		}

		return '(' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
	}


	/**
	 * @phpstan-param array<int|string, mixed> $value
	 */
	private function processWhere(string $type, array $value): string
	{
		if (count($value) === 0) {
			return '1=1';
		}

		$operands = [];
		foreach ($value as $_key => $subValue) {
			if (is_int($_key)) {
				if (!is_array($subValue)) {
					$subValueType = $this->getVariableTypeName($subValue);
					throw new InvalidArgumentException("Modifier %$type requires items with numeric index to be array, $subValueType given.");
				}

				$operand = '(' . $this->process($subValue) . ')';

			} else {
				$key = explode('%', $_key, 2);
				$column = $this->identifierToSql($key[0]);
				$subType = $key[1] ?? 'any';

				if ($subValue === null) {
					$op = ' IS ';
				} elseif (is_array($subValue) && $subType !== 'ex') {
					$op = ' IN ';
				} else {
					$op = ' = ';
				}

				$operand = $column . $op . $this->processModifier($subType, $subValue);
			}

			$operands[] = $operand;
		}

		return implode($type === 'and' ? ' AND ' : ' OR ', $operands);
	}


	/**
	 * @phpstan-param array<string, mixed> $values
	 */
	private function processMultiColumnOr(array $values): string
	{
		if ($this->platform->isSupported(IPlatform::SUPPORT_MULTI_COLUMN_IN)) {
			$keys = [];
			foreach (array_keys(reset($values)) as $key) {
				$keys[] = $this->identifierToSql(explode('%', (string) $key, 2)[0]);
			}
			return '(' . implode(', ', $keys) . ') IN ' . $this->processModifier('any', $values);

		} else {
			$sqls = [];
			foreach ($values as $value) {
				$sqls[] = $this->processWhere('and', $value);
			}
			return '(' . implode(') OR (', $sqls) . ')';
		}
	}


	/**
	 * @param mixed $value
	 * @return float|string
	 */
	protected function getVariableTypeName($value)
	{
		return is_object($value) ? get_class($value) : (is_float($value) && !is_finite($value) ? $value : gettype($value));
	}


	protected function identifierToSql(string $key): string
	{
		return $this->identifiers[$key] ??
			($this->identifiers[$key] = // = intentionally
				substr($key, -2) === '.*'
					? $this->platform->formatIdentifier(substr($key, 0, -2)) . '.*'
					: $this->platform->formatIdentifier($key)
			);
	}
}

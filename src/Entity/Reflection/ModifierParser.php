<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\Reflection\AnnotationsParser;
use Nette\Utils\TokenIterator;
use Nette\Utils\Tokenizer;
use Nextras\Orm\InvalidModifierDefinitionException;
use ReflectionClass;


class ModifierParser
{
	/** @internal */
	const TOKEN_KEYWORD = 1;
	/** @internal */
	const TOKEN_STRING = 2;
	/** @internal */
	const TOKEN_LBRACKET = 3;
	/** @internal */
	const TOKEN_RBRACKET = 4;
	/** @internal */
	const TOKEN_EQUAL = 5;
	/** @internal */
	const TOKEN_SEPARATOR = 6;
	/** @internal */
	const TOKEN_WHITESPACE = 7;
	/** @internal regular expression for single & double quoted PHP string */
	const RE_STRING = '\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"';

	/** @var Tokenizer */
	private $tokenizer;


	public function __construct()
	{
		$this->tokenizer = new Tokenizer([
			self::TOKEN_STRING => self::RE_STRING,
			self::TOKEN_LBRACKET => '\[',
			self::TOKEN_RBRACKET => '\]',
			self::TOKEN_EQUAL => '=',
			self::TOKEN_KEYWORD => '[a-zA-Z0-9_:$.*>\\\\-]+',
			self::TOKEN_SEPARATOR => ',',
			self::TOKEN_WHITESPACE => '\s+',
		]);
	}


	/**
	 * @param  string $input
	 * @return array
	 */
	public function matchModifiers($input)
	{
		preg_match_all('#
			\{(
				(?:
					' . self::RE_STRING . ' |
					[^}]
				)++
			)\}#x', $input, $matches);
		return $matches[1];
	}


	/**
	 * @param  string $string
	 * @param  ReflectionClass $reflectionClass
	 * @return array
	 */
	public function parse($string, ReflectionClass $reflectionClass)
	{
		$tokens = $this->lex($string, $reflectionClass);
		$iterator = new TokenIterator($tokens);
		return [
			$name = $this->processName($iterator),
			$this->processArgs($iterator, $name, false),
		];
	}


	private function lex($input, ReflectionClass $reflectionClass)
	{
		try {
			$tokens = $this->tokenizer->tokenize($input);
		} catch (TokenizerException $e) {
			throw new InvalidModifierDefinitionException('Unable to tokenize the modifier.', 0, $e);
		}

		$tokens = array_filter($tokens, function ($pair) {
			return $pair[2] !== self::TOKEN_WHITESPACE && $pair[2] !== null;
		});
		$tokens = array_values($tokens);

		$expanded = [];
		foreach ($tokens as $token) {
			if ($token[2] === self::TOKEN_STRING) {
				$token[0] = stripslashes(substr($token[0], 1, -1));
				$expanded[] = $token;

			} elseif ($token[2] === self::TOKEN_KEYWORD) {
				$values = $this->processKeyword($token[0], $reflectionClass);
				if (is_array($values)) {
					$count = count($values) - 1;
					foreach ($values as $i => $value) {
						$expanded[] = [$value, $token[1], $token[2]];
						if ($i !== $count) {
							$expanded[] = [',', -1, self::TOKEN_SEPARATOR];
						}
					}
				} else {
					$expanded[] = [$values, $token[1], $token[2]];
				}

			} else {
				$expanded[] = $token;
			}
		}
		return $expanded;
	}


	private function processName(TokenIterator $iterator)
	{
		$iterator->position++;
		if (!isset($iterator->tokens[$iterator->position])) {
			throw new InvalidModifierDefinitionException("Modifier does not have a name.");
		}
		list($value, , $type) = $iterator->currentToken();
		if ($type !== self::TOKEN_KEYWORD) {
			throw new InvalidModifierDefinitionException("Modifier does not have a name.");
		} elseif (isset($iterator->tokens[$iterator->position + 1])) {
			list(, , $type) = $iterator->tokens[$iterator->position + 1];
			if ($type === self::TOKEN_SEPARATOR) {
				throw new InvalidModifierDefinitionException("After the {{$value}}'s modifier name cannot be a comma separator.");
			}
		}

		return $value;
	}


	private function processArgs(TokenIterator $iterator, $modifierName, $inArray)
	{
		$result = [];
		$iterator->position++;
		while (isset($iterator->tokens[$iterator->position])) {
			list($value, , $type) = $iterator->currentToken();

			if ($type === self::TOKEN_RBRACKET) {
				if ($inArray) {
					return $result;
				} else {
					throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} mismatches brackets.");
				}
			} elseif ($type === self::TOKEN_STRING || $type === self::TOKEN_KEYWORD) {
				$iterator->position++;
				list(, , $nextToken) = $iterator->currentToken();

				if ($nextToken === self::TOKEN_EQUAL) {
					$iterator->position++;
					list(, , $nextToken) = $iterator->currentToken();
					$nextValue = $iterator->currentValue();

					if ($nextToken === self::TOKEN_LBRACKET) {
						$result[$value] = $this->processArgs($iterator, $modifierName, true);
					} elseif ($nextToken === self::TOKEN_STRING || $nextToken === self::TOKEN_KEYWORD) {
						$result[$value] = $nextValue;
					} elseif ($nextToken !== null) {
						throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has invalid token after =.");
					}
				} elseif ($type !== null) {
					$iterator->position--;
					$result[] = $value;
				}
			} elseif ($type === self::TOKEN_LBRACKET) {
				$result[] = $this->processArgs($iterator, $modifierName, true);
			} else {
				throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has invalid token, expected string, keyword, or array.");
			}

			$iterator->position++;
			list(, , $type) = $iterator->currentToken();
			if ($type === self::TOKEN_RBRACKET && $inArray) {
				return $result;
			} elseif ($type !== null && $type !== self::TOKEN_SEPARATOR) {
				throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} misses argument separator.");
			}

			$iterator->position++;
		}

		if ($inArray) {
			throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has unclosed array argument.");
		}

		return $result;
	}


	private function processKeyword($value, ReflectionClass $reflectionClass)
	{
		if (strcasecmp($value, 'true') === 0) {
			return true;
		} elseif (strcasecmp($value, 'false') === 0) {
			return false;
		} elseif (strcasecmp($value, 'null') === 0) {
			return null;
		} elseif (is_numeric($value)) {
			return $value * 1;
		} elseif (preg_match('#^[a-z0-9_\\\\]+::[a-z0-9_]*(\\*)?$#i', $value)) {
			list($className, $const) = explode('::', $value, 2);
			if ($className === 'self' || $className === 'static') {
				$reflection = $reflectionClass;
			} else {
				$className = AnnotationsParser::expandClassName($className, $reflectionClass);
				$reflection = new ReflectionClass($className);
			}

			$enum = [];
			$constants = $reflection->getConstants();
			if (strpos($const, '*') !== false) {
				$prefix = rtrim($const, '*');
				$prefixLength = strlen($prefix);
				$count = 0;
				foreach ($constants as $name => $value) {
					if (substr($name, 0, $prefixLength) === $prefix) {
						$enum[$value] = $value;
						$count += 1;
					}
				}
				if ($count === 0) {
					throw new InvalidModifierDefinitionException("No constant matches {$reflection->name}::{$const} pattern.");
				}
			} else {
				if (!array_key_exists($const, $constants)) {
					throw new InvalidModifierDefinitionException("Constant {$reflection->name}::{$const} does not exist.");
				}
				$value = $reflection->getConstant($const);
				$enum[$value] = $value;
			}
			return array_values($enum);
		} else {
			return $value;
		}
	}
}

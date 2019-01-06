<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\Tokenizer\Exception;
use Nette\Tokenizer\Stream;
use Nette\Tokenizer\Token;
use Nette\Tokenizer\Tokenizer;
use Nette\Utils\Reflection;
use Nextras\Orm\InvalidModifierDefinitionException;
use Nextras\Orm\InvalidStateException;
use ReflectionClass;


class ModifierParser
{
	private const TOKEN_KEYWORD = 1;
	private const TOKEN_STRING = 2;
	private const TOKEN_LBRACKET = 3;
	private const TOKEN_RBRACKET = 4;
	private const TOKEN_EQUAL = 5;
	private const TOKEN_SEPARATOR = 6;
	private const TOKEN_WHITESPACE = 7;
	/** @const regular expression for single & double quoted PHP string */
	private const RE_STRING = '\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"';

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


	public function matchModifiers(string $input): array
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
	 * @throws InvalidModifierDefinitionException
	 */
	public function parse(string $string, ReflectionClass $reflectionClass): array
	{
		$iterator = $this->lex($string);
		return [
			$name = $this->processName($iterator),
			$this->processArgs($iterator, $reflectionClass, $name, false),
		];
	}


	private function lex(string $input): Stream
	{
		try {
			$tokens = $this->tokenizer->tokenize($input)->tokens;
		} catch (Exception $e) {
			throw new InvalidModifierDefinitionException('Unable to tokenize the modifier.', 0, $e);
		}

		$tokens = array_filter($tokens, function ($token) {
			return $token->type !== self::TOKEN_WHITESPACE;
		});
		$tokens = array_values($tokens);
		return new Stream($tokens);
	}


	private function processName(Stream $iterator): string
	{
		$iterator->position++;
		$currentToken = $iterator->currentToken();
		if ($currentToken === null) {
			throw new InvalidModifierDefinitionException("Modifier does not have a name.");
		}
		if ($currentToken->type !== self::TOKEN_KEYWORD) {
			throw new InvalidModifierDefinitionException("Modifier does not have a name.");
		} elseif (isset($iterator->tokens[$iterator->position + 1])) {
			$nextToken = $iterator->tokens[$iterator->position + 1];
			if ($nextToken->type === self::TOKEN_SEPARATOR) {
				throw new InvalidModifierDefinitionException("After the {{$currentToken->value}}'s modifier name cannot be a comma separator.");
			}
		}

		return $currentToken->value;
	}


	private function processArgs(Stream $iterator, \ReflectionClass $reflectionClass, string $modifierName, bool $inArray)
	{
		$result = [];
		while (($currentToken = $iterator->nextToken()) !== null) {
			assert($currentToken !== null);
			$type = $currentToken->type;

			if ($type === self::TOKEN_RBRACKET) {
				if ($inArray) {
					return $result;
				} else {
					throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} mismatches brackets.");
				}
			} elseif ($type === self::TOKEN_STRING || $type === self::TOKEN_KEYWORD) {
				$iterator->position++;
				$nextToken = $iterator->currentToken();
				$nextTokenType = $nextToken ? $nextToken->type : null;

				if ($nextTokenType === self::TOKEN_EQUAL) {
					$iterator->position++;
					$nextToken = $iterator->currentToken();
					$nextTokenType = $nextToken ? $nextToken->type : null;

					if ($nextTokenType === self::TOKEN_LBRACKET) {
						$value = $this->processValue($currentToken, $reflectionClass);
						assert(!is_array($value));
						$result[$value] = $this->processArgs($iterator, $reflectionClass, $modifierName, true);
					} elseif ($nextTokenType === self::TOKEN_STRING || $nextTokenType === self::TOKEN_KEYWORD) {
						$value = $this->processValue($currentToken, $reflectionClass);
						assert(!is_array($value));
						assert($nextToken !== null);
						$result[$value] = $this->processValue($nextToken, $reflectionClass);
					} elseif ($nextTokenType !== null) {
						throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has invalid token after =.");
					}
				} else {
					$iterator->position--;
					$value = $this->processValue($currentToken, $reflectionClass);
					if (is_array($value)) {
						foreach ($value as $subValue) {
							$result[] = $subValue;
						}
					} else {
						$result[] = $value;
					}
				}
			} elseif ($type === self::TOKEN_LBRACKET) {
				$result[] = $this->processArgs($iterator, $reflectionClass, $modifierName, true);
			} else {
				throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has invalid token, expected string, keyword, or array.");
			}

			$iterator->position++;
			$currentToken2 = $iterator->currentToken();
			$type = $currentToken2 ? $currentToken2->type : null;
			if ($type === self::TOKEN_RBRACKET && $inArray) {
				return $result;
			} elseif ($type !== null && $type !== self::TOKEN_SEPARATOR) {
				throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} misses argument separator.");
			}
		}

		if ($inArray) {
			throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has unclosed array argument.");
		}

		return $result;
	}


	/**
	 * @return mixed
	 */
	private function processValue(Token $token, \ReflectionClass $reflectionClass)
	{
		if ($token->type === self::TOKEN_STRING) {
			return stripslashes(substr($token->value, 1, -1));

		} elseif ($token->type === self::TOKEN_KEYWORD) {
			return $this->processKeyword($token->value, $reflectionClass);

		} else {
			throw new InvalidStateException();
		}
	}


	private function processKeyword(string $value, ReflectionClass $reflectionClass)
	{
		if (strcasecmp($value, 'true') === 0) {
			return true;
		} elseif (strcasecmp($value, 'false') === 0) {
			return false;
		} elseif (strcasecmp($value, 'null') === 0) {
			return null;
		} elseif (is_numeric($value)) {
			// hack for phpstan
			/** @var int $val */
			$val = $value;
			return $val * 1;
		} elseif (preg_match('#^[a-z0-9_\\\\]+::[a-z0-9_]*(\\*)?$#i', $value)) {
			[$className, $const] = explode('::', $value, 2);
			if ($className === 'self' || $className === 'static') {
				$reflection = $reflectionClass;
			} else {
				$className = Reflection::expandClassName($className, $reflectionClass);
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

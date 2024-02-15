<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use BackedEnum;
use Nette\Utils\Reflection;
use Nextras\Orm\Entity\Reflection\Parser\Token;
use Nextras\Orm\Entity\Reflection\Parser\TokenLexer;
use Nextras\Orm\Entity\Reflection\Parser\TokenStream;
use Nextras\Orm\Exception\InvalidStateException;
use ReflectionClass;
use ReflectionEnum;


class ModifierParser
{
	private TokenLexer $tokenLexer;


	public function __construct()
	{
		$this->tokenLexer = new TokenLexer();
	}


	/**
	 * @return list<string>
	 */
	public function matchModifiers(string $input): array
	{
		preg_match_all('#
			\{(
				(?:
					' . TokenLexer::RE_STRING . ' |
					[^}]
				)++
			)}#x', $input, $matches);
		return $matches[1];
	}


	/**
	 * @param ReflectionClass<object> $reflectionClass
	 * @return array{string, array<int|string, mixed>}
	 * @throws InvalidModifierDefinitionException
	 */
	public function parse(string $string, ReflectionClass $reflectionClass): array
	{
		$iterator = $this->tokenLexer->lex($string);
		return [
			$name = $this->processName($iterator),
			$this->processArgs($iterator, $reflectionClass, $name, false),
		];
	}


	private function processName(TokenStream $iterator): string
	{
		$iterator->position++;
		$currentToken = $iterator->currentToken();
		if ($currentToken === null) {
			throw new InvalidModifierDefinitionException("Modifier does not have a name.");
		}
		if ($currentToken->type !== Token::KEYWORD) {
			throw new InvalidModifierDefinitionException("Modifier does not have a name.");
		} elseif (isset($iterator->tokens[$iterator->position + 1])) {
			$nextToken = $iterator->tokens[$iterator->position + 1];
			if ($nextToken->type === Token::SEPARATOR) {
				throw new InvalidModifierDefinitionException("After the {{$currentToken->value}}'s modifier name cannot be a comma separator.");
			}
		}

		return $currentToken->value;
	}


	/**
	 * @param ReflectionClass<object> $reflectionClass
	 * @return array<int|string, mixed>
	 */
	private function processArgs(
		TokenStream $iterator,
		ReflectionClass $reflectionClass,
		string $modifierName,
		bool $inArray,
	): array
	{
		$result = [];
		while (($currentToken = $iterator->nextToken()) !== null) {
			$type = $currentToken->type;

			if ($type === Token::RBRACKET) {
				if ($inArray) {
					return $result;
				} else {
					throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} mismatches brackets.");
				}
			} elseif ($type === Token::STRING || $type === Token::KEYWORD) {
				$iterator->position++;
				$nextToken = $iterator->currentToken();
				$nextTokenType = $nextToken?->type;

				if ($nextTokenType === Token::EQUAL) {
					$iterator->position++;
					$nextToken = $iterator->currentToken();
					$nextTokenType = $nextToken?->type;

					if ($nextTokenType === Token::LBRACKET) {
						$value = $this->processValue($currentToken, $reflectionClass);
						assert(!is_array($value));
						$result[$value] = $this->processArgs($iterator, $reflectionClass, $modifierName, true);
					} elseif ($nextTokenType === Token::STRING || $nextTokenType === Token::KEYWORD) {
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
			} elseif ($type === Token::LBRACKET) {
				$result[] = $this->processArgs($iterator, $reflectionClass, $modifierName, true);
			} else {
				throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has invalid token, expected string, keyword, or array.");
			}

			$iterator->position++;
			$currentToken2 = $iterator->currentToken();
			$type = $currentToken2?->type;
			if ($type === Token::RBRACKET && $inArray) {
				return $result;
			} elseif ($type !== null && $type !== Token::SEPARATOR) {
				throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} misses argument separator.");
			}
		}

		if ($inArray) {
			throw new InvalidModifierDefinitionException("Modifier {{$modifierName}} has unclosed array argument.");
		}

		return $result;
	}


	/**
	 * @param ReflectionClass<object> $reflectionClass
	 */
	private function processValue(Token $token, ReflectionClass $reflectionClass): mixed
	{
		if ($token->type === Token::STRING) {
			return stripslashes(substr($token->value, 1, -1));
		} elseif ($token->type === Token::KEYWORD) {
			return $this->processKeyword($token->value, $reflectionClass);
		} else {
			throw new InvalidStateException();
		}
	}


	/**
	 * @param ReflectionClass<object> $reflectionClass
	 */
	private function processKeyword(string $value, ReflectionClass $reflectionClass): mixed
	{
		if (strcasecmp($value, 'true') === 0) {
			return true;
		} elseif (strcasecmp($value, 'false') === 0) {
			return false;
		} elseif (strcasecmp($value, 'null') === 0) {
			return null;
		} elseif (is_numeric($value)) {
			return $value * 1;
		} elseif (preg_match('#^[a-z0-9_\\\\]+::[a-z0-9_]*(\\*)?$#i', $value) === 1) {
			[$className, $const] = explode('::', $value, 2);
			if ($className === 'self' || $className === 'static') {
				$reflection = $reflectionClass;
			} else {
				$className = Reflection::expandClassName($className, $reflectionClass);
				assert(class_exists($className) || interface_exists($className));
				$reflection = new ReflectionClass($className);
			}

			if ($reflection->isEnum() && is_subclass_of($className, BackedEnum::class)) {
				return (new ReflectionEnum($className))->getCase($const)->getValue();
			}
			$enum = [];
			$constants = $reflection->getConstants();
			if (str_contains($const, '*')) {
				$prefix = rtrim($const, '*');
				$prefixLength = strlen($prefix);
				$count = 0;
				foreach ($constants as $name => $constantValue) {
					if (substr($name, 0, $prefixLength) === $prefix) {
						$enum[$constantValue] = $constantValue;
						$count += 1;
					}
				}
				if ($count === 0) {
					throw new InvalidModifierDefinitionException("No constant matches $reflection->name::$const pattern.");
				}
			} else {
				if (!array_key_exists($const, $constants)) {
					throw new InvalidModifierDefinitionException("Constant $reflection->name::$const does not exist.");
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

<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection\Parser;


use Nextras\Orm\Exception\InvalidStateException;


/**
 * @internal
 */
class TokenLexer
{
	/** @const regular expression for single & double quoted PHP string */
	public const RE_STRING = '\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"';

	private string $tokenizerRegexp;
	/** @var list<int> */
	private array $tokenizerTypes;


	public function __construct()
	{
		$patterns = [
			Token::STRING => self::RE_STRING,
			Token::LBRACKET => '\[',
			Token::RBRACKET => '\]',
			Token::EQUAL => '=',
			Token::KEYWORD => '[a-zA-Z0-9_:$.*>\\\\-]+',
			Token::SEPARATOR => ',',
			Token::WHITESPACE => '\s+',
		];
		$this->tokenizerRegexp = '~(' . implode(')|(', $patterns) . ')~A';
		$this->tokenizerTypes = array_keys($patterns);
	}


	public function lex(string $input): TokenStream
	{
		$tokens = $this->tokenize($input);
		$tokens = array_filter($tokens, function ($token): bool {
			return $token->type !== Token::WHITESPACE;
		});
		$tokens = array_values($tokens);
		return new TokenStream($tokens);
	}


	/**
	 * @return list<Token>
	 */
	private function tokenize(string $input): array
	{
		preg_match_all($this->tokenizerRegexp, $input, $tokens, PREG_SET_ORDER);
		if (preg_last_error() !== PREG_NO_ERROR) {
			throw new InvalidStateException(array_flip(get_defined_constants(true)['pcre'])[preg_last_error()]);
		}

		$len = 0;
		$result = [];
		$count = count($this->tokenizerTypes);
		foreach ($tokens as $token) {
			$type = null;
			for ($i = 1; $i <= $count; $i++) {
				if (!isset($token[$i])) {
					break;
				} elseif ($token[$i] !== '') {
					$type = $this->tokenizerTypes[$i - 1];
					break;
				}
			}
			if ($type === null) {
				throw new InvalidStateException("Unknown token type for '$token[0]'.");
			}

			$token =  new Token($token[0], $type, $len);
			$result[] = $token;
			$len += strlen($token->value);
		}

		if ($len !== strlen($input)) {
			[$line, $col] = self::getCoordinates($input, $len);
			$unexpectedToken = str_replace("\n", '\n', substr($input, $len, 10));
			throw new InvalidStateException("Unexpected '$unexpectedToken' on line $line, column $col.");
		}

		return $result;
	}


	/**
	 * Returns (line, column) position of token in input string.
	 * @return array{int, int}
	 */
	private static function getCoordinates(string $text, int $offset): array
	{
		$text = substr($text, 0, $offset);
		$pos = strrpos("\n" . $text, "\n");
		return [substr_count($text, "\n") + 1, $offset - ($pos === false ? 0 : $pos) + 1];
	}
}

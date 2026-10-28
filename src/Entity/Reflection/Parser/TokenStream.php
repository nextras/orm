<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection\Parser;


/**
 * @internal
 */
class TokenStream
{
	public int $position = -1;


	public function __construct(
		/** @var array<array-key, Token> */
		public readonly array $tokens,
	)
	{
	}


	/**
	 * Returns current token.
	 */
	public function currentToken(): Token|null
	{
		return $this->tokens[$this->position] ?? null;
	}


	/**
	 * Returns next token.
	 */
	public function nextToken(): Token|null
	{
		$this->position++;
		return $this->tokens[$this->position] ?? null;
	}
}

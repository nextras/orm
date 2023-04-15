<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection\Parser;


/**
 * @internal
 */
class TokenStream
{
	public int $position = -1;


	/**
	 * @param array<array-key, Token> $tokens
	 */
	public function __construct(
		public readonly array $tokens,
	)
	{
	}


	/**
	 * Returns current token.
	 */
	public function currentToken(): ?Token
	{
		return $this->tokens[$this->position] ?? null;
	}


	/**
	 * Returns next token.
	 */
	public function nextToken(): ?Token
	{
		$this->position += 1;
		return $this->tokens[$this->position] ?? null;
	}
}

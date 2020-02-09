<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;


class DbalExpressionResult
{
	/** @var array<mixed> */
	public $args;

	/** @var bool */
	public $isHavingClause;

	/** @var (callable(mixed): mixed)|null */
	public $valueNormalizer;


	/**
	 * @param array<mixed> $args
	 */
	public function __construct(
		array $args,
		bool $isHavingClause = false,
		?callable $valueNormalizer = null
	)
	{
		$this->args = $args;
		$this->isHavingClause = $isHavingClause;
		$this->valueNormalizer = $valueNormalizer;
	}


	public function append(string $expression, ...$args): DbalExpressionResult
	{
		\array_unshift($args, $this->args);
		\array_unshift($args, "%ex $expression");
		return new DbalExpressionResult($args, $this->isHavingClause);
	}
}

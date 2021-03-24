<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Filter;

class Filter
{

	/** @var FindFilter */
	private $find;

	/** @var OrderFilter */
	private $order;

	/** @var int|null */
	private $limitCount;

	/** @var int|null */
	private $limitOffset;

	public function __construct()
	{
		$this->find = new FindFilter();
		$this->order = new OrderFilter();
	}

	public function find(): FindFilter
	{
		return $this->find;
	}

	public function order(): OrderFilter
	{
		return $this->order;
	}

	public function limit(int $count, ?int $offset = null): void
	{
		$this->limitCount = $count;
		$this->limitOffset = $offset;
	}

	/**
	 * @return array{int|null, int|null}
	 */
	public function getLimit(): array
	{
		return [
			$this->limitCount,
			$this->limitOffset,
		];
	}

}

<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use ArrayIterator;
use Nextras\Orm\NotImplementedException;


class ArrayCollection implements ICollection
{
	/** @var array */
	private $data;


	public function __construct(array $data)
	{
		$this->data = $data;
	}


	public function getIterator()
	{
		return new ArrayIterator($this->data);
	}


	public function orderBy($column, $direction = self::ASC)
	{
		throw new NotImplementedException();
	}


	public function limitBy($limit, $offset = NULL)
	{
		throw new NotImplementedException();
	}


	public function fetch()
	{
		throw new NotImplementedException();
	}


	public function fetchAll()
	{
		throw new NotImplementedException();
	}


	public function fetchAssoc($assoc)
	{
		throw new NotImplementedException();
	}


	public function fetchPairs($key = NULL, $value = NULL)
	{
		throw new NotImplementedException();
	}


	public function findBy(array $where)
	{
		throw new NotImplementedException();
	}


	public function getBy(array $where)
	{
		throw new NotImplementedException();
	}


	public function toCollection()
	{
		throw new NotImplementedException();
	}


	public function count()
	{
		return count($this->data);
	}

}

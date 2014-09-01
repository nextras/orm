<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\TestHelper;

use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\Memory\ArrayMapper;
use Nextras\Orm\Repository\IRepository;


class TestMapper extends ArrayMapper
{
	/** @var array */
	protected $storage = [];

	/** @var IMapper */
	protected $originMapper;


	public function __construct(IMapper $originMapper)
	{
		$this->originMapper = $originMapper;
	}


	public function setRepository(IRepository $repository)
	{
		parent::setRepository($repository);
		$this->originMapper->setRepository($repository);
	}


	protected function readData()
	{
		return $this->storage;
	}


	protected function saveData(array $data)
	{
		$this->storage = $data;
	}

}

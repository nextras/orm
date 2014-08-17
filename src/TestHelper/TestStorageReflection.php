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
use Nextras\Orm\StorageReflection\CommonReflection;
use Nextras\Orm\StorageReflection\IStorageReflection;


class TestStorageReflection extends CommonReflection
{
	/** @var IStorageReflection */
	private $originalStorageReflection;


	public function __construct(IMapper $mapper, IStorageReflection $originalStorageReflection)
	{
		parent::__construct($mapper);
		$this->originalStorageReflection = $originalStorageReflection;
	}


	public function getEntityPrimaryKey()
	{
		return $this->originalStorageReflection->getEntityPrimaryKey();
	}


	public function getStoragePrimaryKey()
	{
		return $this->getEntityPrimaryKey();
	}

}

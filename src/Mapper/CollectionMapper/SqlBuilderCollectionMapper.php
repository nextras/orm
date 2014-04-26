<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\CollectionMapper;

use Nette\Database\Context;
use Nette\Database\Table\SqlBuilder;
use Nextras\Orm\Mapper\NetteConditionParser;
use Nextras\Orm\Repository\IRepository;


/**
 * SqlBuilder based collection mapper.
 */
class SqlBuilderCollectionMapper extends CollectionMapper
{

	public function __construct(IRepository $repository, Context $databaseContext, $tableName, SqlBuilder $builder)
	{
		$this->repository = $repository;
		$this->databaseContext = $databaseContext;
		$this->tableName = $tableName;

		$this->builder = $builder;
		$this->parser = new NetteConditionParser($repository->getModel(), $repository->getModel()->getMetadataStorage());
	}

}

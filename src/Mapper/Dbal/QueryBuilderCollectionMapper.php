<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Repository\IRepository;


/**
 * QueryBuilder based collection mapper.
 */
class QueryBuilderCollectionMapper extends CollectionMapper
{

	public function __construct(IRepository $repository, Connection $connection, QueryBuilder $builder)
	{
		$this->repository = $repository;
		$this->connection = $connection;
		$this->builder = $builder;
	}

}

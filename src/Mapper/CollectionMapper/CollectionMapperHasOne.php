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
use Nette\Object;
use Nextras\Orm\Entity\Collection\EntityContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Repository\IRepository;


/**
 * ManyHasOneCollectionMapper for Nette Framework.
 */
class CollectionMapperHasOne extends Object implements ICollectionMapperHasOne
{
	/** @var Context */
	protected $databaseContext;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IMapper */
	protected $targetMapper;

	/** @var IRepository */
	protected $targetRepository;

	/** @var ICollectionMapper */
	protected $collectionMapper;

	/** @var EntityContainer[] */
	protected $cacheEntityContainers;


	public function __construct(Context $databaseContext, IMapper $targetMapper, ICollectionMapper $collectionMapper, PropertyMetadata $metadata)
	{
		$this->databaseContext = $databaseContext;
		$this->targetMapper = $targetMapper;
		$this->targetRepository = $targetMapper->getRepository();
		$this->collectionMapper = $collectionMapper;
		$this->metadata = $metadata;
	}


	public function getItem(IEntity $parent)
	{
		$container = $this->execute($parent);
		return $container->getItem($parent->getForeignKey($this->metadata->name));
	}


	protected function execute(IEntity $parent)
	{
		$preloadIterator = $parent->getPreloadContainer();
		$builder = $this->collectionMapper->getSqlBuilder();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheEntityContainers[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadPrimaryValues() : [$parent->getValue('id')];
		$data = $this->fetch(clone $builder, $values);
		return $data;
	}


	protected function fetch(SqlBuilder $builder, array $values)
	{
		$entities = $this->targetMapper->findAll()->findBy(['this->' . $this->metadata->args[1] . '.id' => $values]);

		$data = [];
		foreach ($entities as $entity) {
			$data[$entity->id] = $entity;
		}

		return new EntityContainer($data);
	}

}

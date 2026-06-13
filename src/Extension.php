<?php declare(strict_types = 1);

namespace Nextras\Orm;


use Nextras\Orm\Bridges\NetteDI\OrmExtension;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;


/**
 * An entry point for various extensions for Orm.
 *
 * To implement the extension, override methods you want to utilize.
 * Registering a new configurator extension is done through {@see OrmExtension} for Nette DIC.
 */
abstract class Extension
{
	/**
	 * Modifies the model instance.
	 *
	 * Runs once when the model is instantiated.
	 */
	public function configureModel(
		IModel $model,
	): void
	{
	}

	/**
	 * Modifies the repository instance.
	 *
	 * Runs every time the mapper is instantiated in runtime.
	 *
	 * @param IRepository<*> $repository
	 */
	public function configureRepository(
		IRepository $repository,
	): void
	{
	}


	/**
	 * Modifies the mapper instance.
	 *
	 * Runs every time the mapper is instantiated in runtime.
	 *
	 * @param IMapper<*> $mapper
	 */
	public function configureMapper(
		IMapper $mapper,
	): void
	{
	}


	/**
	 * Modifies the entity metadata instance.
	 *
	 * Runs when entity property metadata are parsed during compile time (before cache serialization).
	 */
	public function configureEntityMetadata(
		EntityMetadata $metadata,
	): void
	{
	}


	/**
	 * Modifies the entity property metadata instance.
	 *
	 * Runs when entity property metadata are parsed during compile time (before cache serialization).
	 */
	public function configureEntityPropertyMetadata(
		EntityMetadata $entityMetadata,
		PropertyMetadata $propertyMetadata,
		TypeNode $propertyType,
	): void
	{
	}
}

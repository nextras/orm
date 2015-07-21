<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\QueryBuilderHelper;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Mapper\Dbal\StorageReflection\StorageReflection;
use NextrasTests\Orm\TestCase;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class QueryBuilderHelperTest extends TestCase
{
	/** @var QueryBuilderHelper */
	private $builderHelper;

	/** @var StorageReflection|MockInterface */
	private $reflection;

	/** @var MetadataStorage|MockInterface */
	private $metadataStorage;

	/** @var Model|MockInterface */
	private $model;

	/** @var DbalMapper|MockInterface */
	private $mapper;

	/** @var EntityMetadata|MockInterface */
	private $entityMetadata;

	/** @var QueryBuilder|MockInterface */
	private $queryBuilder;


	protected function setUp()
	{
		parent::setUp();

		$this->reflection = Mockery::mock('Nextras\Orm\StorageReflection\IDbStorageReflection');
		$this->model = Mockery::mock('Nextras\Orm\Model\IModel');
		$this->metadataStorage = Mockery::mock('Nextras\Orm\Model\MetadataStorage');
		$this->mapper = Mockery::mock('Nextras\Orm\Mapper\Dbal\DbalMapper');
		$this->entityMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');
		$this->queryBuilder = Mockery::mock('Nextras\Dbal\QueryBuilder\QueryBuilder');

		$this->model->shouldReceive('getMetadataStorage')->once()->andReturn($this->metadataStorage);
		$this->builderHelper = new QueryBuilderHelper($this->model, $this->mapper);

		Environment::$checkAssertions = FALSE;
	}


	public function testHasOne()
	{
		$this->mapper->shouldReceive('getRepository')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getEntityClassNames')->once()->andReturn(['EntityClass']);
		$this->metadataStorage->shouldReceive('get')->once()->with('EntityClass')->andReturn($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
		$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);

		$propertyMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata->relationship->entity = 'Author';
		$propertyMetadata->relationship->repository = 'AuthorsRepository';
		$propertyMetadata->relationship->type = PropertyRelationshipMetadata::MANY_HAS_ONE;

		// translator
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('translator')->andReturn($propertyMetadata);
		$this->model->shouldReceive('getRepository')->once()->with('AuthorsRepository')->andReturn($this->model);
		$this->model->shouldReceive('getMapper')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);
		$this->reflection->shouldReceive('getStoragePrimaryKey')->once()->andReturn(['id']);
		$this->reflection->shouldReceive('convertEntityToStorageKey')->once()->with('translator')->andReturn('translator_id');
		$this->mapper->shouldReceive('getTableName')->once()->andReturn('authors');
		$this->metadataStorage->shouldReceive('get')->once()->with('Author')->andReturn($this->entityMetadata);

		// name
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('name');
		$this->reflection->shouldReceive('convertEntityToStorageKey')->once()->with('name')->andReturn('name');

		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('books', 'authors', 'translator', '[books.translator_id] = [translator.id]');
		$this->queryBuilder->shouldReceive('addOrderBy')->once()->with('translator.name');

		$this->builderHelper->processOrderByExpression('this->translator->name', ICollection::ASC, $this->queryBuilder);
	}


	public function testOneHasManyAndManyHasMany()
	{
		$this->mapper->shouldReceive('getRepository')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getEntityClassNames')->once()->andReturn(['EntityClass']);
		$this->metadataStorage->shouldReceive('get')->once()->with('EntityClass')->andReturn($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('authors');
		$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);

		$propertyMetadata1 = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata1->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata1->relationship->entity = 'Book';
		$propertyMetadata1->relationship->repository = 'BooksRepository';
		$propertyMetadata1->relationship->property = 'translator';
		$propertyMetadata1->relationship->type = PropertyRelationshipMetadata::ONE_HAS_MANY;

		$propertyMetadata2 = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata2->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata2->relationship->entity = 'Tag';
		$propertyMetadata2->relationship->repository = 'TagsRepository';
		$propertyMetadata2->relationship->property = 'books';
		$propertyMetadata2->relationship->type = PropertyRelationshipMetadata::MANY_HAS_MANY;
		$propertyMetadata2->relationship->isMain = TRUE;

		// translated books
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('translatedBooks')->andReturn($propertyMetadata1);
		$this->model->shouldReceive('getRepository')->once()->with('BooksRepository')->andReturn($this->model);
		$this->model->shouldReceive('getMapper')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);
		$this->reflection->shouldReceive('convertEntityToStorageKey')->once()->with('translator')->andReturn('translator_id');
		$this->reflection->shouldReceive('getStoragePrimaryKey')->once()->andReturn(['id']);
		$this->mapper->shouldReceive('getTableName')->once()->andReturn('books');
		$this->metadataStorage->shouldReceive('get')->once()->with('Book')->andReturn($this->entityMetadata);

		// tags
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('tags')->andReturn($propertyMetadata2);
		$this->model->shouldReceive('getRepository')->once()->with('TagsRepository')->andReturn($this->model);
		$this->model->shouldReceive('getMapper')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);
		$this->mapper->shouldReceive('getManyHasManyParameters')->once()->with($propertyMetadata2, $this->mapper)->andReturn(['books_x_tags', ['book_id', 'tag_id']]);
		$this->reflection->shouldReceive('getStoragePrimaryKey')->twice()->andReturn(['id']);
		$this->mapper->shouldReceive('getTableName')->once()->andReturn('tags');
		$this->metadataStorage->shouldReceive('get')->once()->with('Tag')->andReturn($this->entityMetadata);

		// name
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('name');
		$this->reflection->shouldReceive('convertEntityToStorageKey')->once()->with('name')->andReturn('name');


		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('authors', 'books', 'translatedBooks', '[authors.id] = [translatedBooks.translator_id]');
		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('translatedBooks', 'books_x_tags', 'books_x_tags', '[translatedBooks.id] = [books_x_tags.book_id]');
		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('books_x_tags', 'tags', 'tags_', '[books_x_tags.tag_id] = [tags_.id]');
		$this->queryBuilder->shouldReceive('andWhere')->once()->with('tags_.name IN %any', ['tag_name']);

		$this->builderHelper->processWhereExpression('this->translatedBooks->tags->name', ['tag_name'], $this->queryBuilder, $needDistinct);
		Assert::true($needDistinct);
	}


	public function testNotEntityProperty()
	{
		Assert::throws(function () {
			$this->mapper->shouldReceive('getRepository')->once()->andReturn($this->mapper);
			$this->mapper->shouldReceive('getEntityClassNames')->once()->andReturn(['EntityClass']);
			$this->metadataStorage->shouldReceive('get')->once()->with('EntityClass')->andReturn($this->entityMetadata);
			$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
			$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);

			$this->entityMetadata->shouldReceive('getClassName')->andReturn('Entity');
			$this->entityMetadata->shouldReceive('getProperty')->with('unknown')->andThrow('Nextras\Orm\InvalidArgumentException');

			$this->builderHelper->processOrderByExpression('this->unknown->test', ICollection::ASC, $this->queryBuilder);
		}, 'Nextras\Orm\InvalidArgumentException');


		Assert::throws(function () {
			$this->mapper->shouldReceive('getRepository')->once()->andReturn($this->mapper);
			$this->mapper->shouldReceive('getEntityClassNames')->once()->andReturn(['EntityClass']);
			$this->metadataStorage->shouldReceive('get')->once()->with('EntityClass')->andReturn($this->entityMetadata);
			$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
			$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);

			$propertyMetadata = mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
			$this->entityMetadata->shouldReceive('getProperty')->with('name')->andReturn($propertyMetadata);

			$this->builderHelper->processOrderByExpression('this->name->test', ICollection::ASC, $this->queryBuilder);
		}, 'Nextras\Orm\InvalidArgumentException');
	}


	public function testOperators()
	{
		$this->mapper->shouldReceive('getRepository')->times(6)->andReturn($this->mapper);
		$this->mapper->shouldReceive('getEntityClassNames')->times(6)->andReturn(['EntityClass']);
		$this->metadataStorage->shouldReceive('get')->times(6)->with('EntityClass')->andReturn($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->times(6)->andReturn('books');
		$this->mapper->shouldReceive('getStorageReflection')->times(6)->andReturn($this->reflection);

		$this->reflection->shouldReceive('convertEntityToStorageKey')->times(6)->with('id')->andReturn('id');
		$this->entityMetadata->shouldReceive('getProperty')->times(6)->with('id');
		$this->entityMetadata->shouldReceive('getPrimaryKey')->times(6)->andReturn(['id']);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('books.id = %any', 1);
		$this->builderHelper->processWhereExpression('id', 1, $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('books.id != %any', 1);
		$this->builderHelper->processWhereExpression('id!', 1, $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('books.id != %any', 1);
		$this->builderHelper->processWhereExpression('id!=', 1, $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('books.id IN %any', [1, 2]);
		$this->builderHelper->processWhereExpression('id', [1, 2], $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('books.id NOT IN %any', [1, 2]);
		$this->builderHelper->processWhereExpression('id!', [1, 2], $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('books.id IS NOT %any', NULL);
		$this->builderHelper->processWhereExpression('id!=', NULL, $this->queryBuilder, $distinctNeeeded);
	}

}


$conditionParserTest = new QueryBuilderHelperTest($dic);
$conditionParserTest->run();

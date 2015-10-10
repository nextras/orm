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
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\QueryBuilderHelper;
use Nextras\Orm\Model\IModel;
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

		$this->reflection = Mockery::mock(IDbStorageReflection::class);
		$this->model = Mockery::mock(IModel::class);
		$this->metadataStorage = Mockery::mock(MetadataStorage::class);
		$this->mapper = Mockery::mock(DbalMapper::class);
		$this->entityMetadata = Mockery::mock(EntityMetadata::class);
		$this->queryBuilder = Mockery::mock(QueryBuilder::class);

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

		$propertyMetadata = Mockery::mock(PropertyMetadata::class);
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
		$this->reflection->shouldReceive('convertEntityToStorage')->once()->with(['name' => NULL])->andReturn(['name' => NULL]);

		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('books', 'authors', 'translator', '[books.translator_id] = [translator.id]');
		$this->queryBuilder->shouldReceive('addOrderBy')->once()->with('[translator.name]');

		$this->builderHelper->processOrderByExpression('this->translator->name', ICollection::ASC, $this->queryBuilder);
	}


	public function testOneHasManyAndManyHasMany()
	{
		$this->mapper->shouldReceive('getRepository')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getEntityClassNames')->once()->andReturn(['EntityClass']);
		$this->metadataStorage->shouldReceive('get')->once()->with('EntityClass')->andReturn($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('authors');
		$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);

		$propertyMetadata1 = Mockery::mock(PropertyMetadata::class);
		$propertyMetadata1->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata1->relationship->entity = 'Book';
		$propertyMetadata1->relationship->repository = 'BooksRepository';
		$propertyMetadata1->relationship->property = 'translator';
		$propertyMetadata1->relationship->type = PropertyRelationshipMetadata::ONE_HAS_MANY;

		$propertyMetadata2 = Mockery::mock(PropertyMetadata::class);
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
		$this->reflection->shouldReceive('convertEntityToStorage')->once()->with(['name' => ['tag_name']])->andReturn(['name' => ['tag_name']]);


		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('authors', 'books', 'translatedBooks', '[authors.id] = [translatedBooks.translator_id]');
		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('translatedBooks', 'books_x_tags', 'books_x_tags', '[translatedBooks.id] = [books_x_tags.book_id]');
		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('books_x_tags', 'tags', 'tags_', '[books_x_tags.tag_id] = [tags_.id]');
		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[tags_.name] IN %any', ['tag_name']);

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
			$this->entityMetadata->shouldReceive('getProperty')->with('unknown')->andThrow(InvalidArgumentException::class);

			$this->builderHelper->processOrderByExpression('this->unknown->test', ICollection::ASC, $this->queryBuilder);
		}, InvalidArgumentException::class);


		Assert::throws(function () {
			$this->mapper->shouldReceive('getRepository')->once()->andReturn($this->mapper);
			$this->mapper->shouldReceive('getEntityClassNames')->once()->andReturn(['EntityClass']);
			$this->metadataStorage->shouldReceive('get')->once()->with('EntityClass')->andReturn($this->entityMetadata);
			$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
			$this->mapper->shouldReceive('getStorageReflection')->once()->andReturn($this->reflection);

			$propertyMetadata = mockery::mock(PropertyMetadata::class);
			$this->entityMetadata->shouldReceive('getProperty')->with('name')->andReturn($propertyMetadata);

			$this->builderHelper->processOrderByExpression('this->name->test', ICollection::ASC, $this->queryBuilder);
		}, InvalidArgumentException::class);
	}


	public function testOperators()
	{
		$this->mapper->shouldReceive('getRepository')->times(6)->andReturn($this->mapper);
		$this->mapper->shouldReceive('getEntityClassNames')->times(6)->andReturn(['EntityClass']);
		$this->metadataStorage->shouldReceive('get')->times(6)->with('EntityClass')->andReturn($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->times(6)->andReturn('books');
		$this->mapper->shouldReceive('getStorageReflection')->times(6)->andReturn($this->reflection);

		$this->reflection->shouldReceive('convertEntityToStorage')->times(3)->with(['id' => 1])->andReturn(['id' => 1]);
		$this->reflection->shouldReceive('convertEntityToStorage')->times(2)->with(['id' => [1, 2]])->andReturn(['id' => [1, 2]]);
		$this->reflection->shouldReceive('convertEntityToStorage')->times(1)->with(['id' => NULL])->andReturn(['id' => NULL]);
		$this->entityMetadata->shouldReceive('getProperty')->times(6)->with('id');
		$this->entityMetadata->shouldReceive('getPrimaryKey')->times(6)->andReturn(['id']);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[books.id] = %any', 1);
		$this->builderHelper->processWhereExpression('id', 1, $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[books.id] != %any', 1);
		$this->builderHelper->processWhereExpression('id!', 1, $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[books.id] != %any', 1);
		$this->builderHelper->processWhereExpression('id!=', 1, $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[books.id] IN %any', [1, 2]);
		$this->builderHelper->processWhereExpression('id', [1, 2], $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[books.id] NOT IN %any', [1, 2]);
		$this->builderHelper->processWhereExpression('id!', [1, 2], $this->queryBuilder, $distinctNeeeded);

		$this->queryBuilder->shouldReceive('andWhere')->once()->with('[books.id] IS NOT %any', NULL);
		$this->builderHelper->processWhereExpression('id!=', NULL, $this->queryBuilder, $distinctNeeeded);
	}

}


$conditionParserTest = new QueryBuilderHelperTest($dic);
$conditionParserTest->run();

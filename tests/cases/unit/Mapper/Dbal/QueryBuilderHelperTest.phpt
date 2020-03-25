<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;

use Mockery;
use Mockery\MockInterface;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class QueryBuilderHelperTest extends TestCase
{
	/** @var DbalQueryBuilderHelper */
	private $builderHelper;

	/** @var Conventions|MockInterface */
	private $conventions;

	/** @var MetadataStorage|MockInterface */
	private $metadataStorage;

	/** @var Model|MockInterface */
	private $model;

	/** @var IRepository|MockInterface */
	private $repository;

	/** @var DbalMapper|MockInterface */
	private $mapper;

	/** @var EntityMetadata|MockInterface */
	private $entityMetadata;

	/** @var QueryBuilder|MockInterface */
	private $queryBuilder;


	protected function setUp()
	{
		parent::setUp();

		$this->conventions = Mockery::mock(IConventions::class);
		$this->model = Mockery::mock(IModel::class);
		$this->repository = Mockery::mock(IRepository::class);
		$this->metadataStorage = Mockery::mock(MetadataStorage::class);
		$this->mapper = Mockery::mock(DbalMapper::class);
		$this->mapper->shouldReceive('getDatabasePlatform->getName')->andReturn('pgsql');
		$this->entityMetadata = Mockery::mock(EntityMetadata::class);
		$this->queryBuilder = Mockery::mock(QueryBuilder::class);

		$this->builderHelper = new DbalQueryBuilderHelper($this->model, $this->repository, $this->mapper);

		Environment::$checkAssertions = false;
	}


	public function testHasOne()
	{
		$this->mapper->shouldReceive('getRepository')->once()->andReturn($repository = Mockery::mock(IRepository::class));
		$repository->shouldReceive('getEntityMetadata')->once()->andReturns($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
		$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);

		$propertyMetadata = Mockery::mock(PropertyMetadata::class);
		$propertyMetadata->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata->relationship->entity = 'Author';
		$propertyMetadata->relationship->repository = 'AuthorsRepository';
		$propertyMetadata->relationship->type = PropertyRelationshipMetadata::MANY_HAS_ONE;
		$propertyMetadata->relationship->entityMetadata = $this->entityMetadata;

		// translator
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('translator')->andReturn($propertyMetadata);
		$this->model->shouldReceive('getRepository')->once()->with('AuthorsRepository')->andReturn($repository = Mockery::mock(IRepository::class));
		$repository->shouldReceive('getMapper')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);
		$this->conventions->shouldReceive('getStoragePrimaryKey')->once()->andReturn(['id']);
		$this->conventions->shouldReceive('convertEntityToStorageKey')->once()->with('translator')->andReturn('translator_id');
		$this->mapper->shouldReceive('getTableName')->once()->andReturn('authors');

		// name
		$namePropertyMetadata = new PropertyMetadata();
		$namePropertyMetadata->name = 'name';
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('name')->andReturn($namePropertyMetadata);
		$this->conventions->shouldReceive('convertEntityToStorageKey')->once()->with('name')->andReturn('name');

		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('books', '[authors]', 'translator', '[books.translator_id] = [translator.id]');
		$columnExpr = $this->builderHelper->processPropertyExpr($this->queryBuilder, 'translator->name')->args[1];
		Assert::same('translator.name', $columnExpr);
	}


	public function testOneHasManyAndManyHasMany()
	{
		$this->mapper->shouldReceive('getRepository')->once()->andReturn($repository = Mockery::mock(IRepository::class));
		$repository->shouldReceive('getEntityMetadata')->once()->andReturns($this->entityMetadata);
		$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('authors');
		$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);

		$propertyMetadata1 = Mockery::mock(PropertyMetadata::class);
		$propertyMetadata1->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata1->relationship->entity = 'Book';
		$propertyMetadata1->relationship->repository = 'BooksRepository';
		$propertyMetadata1->relationship->property = 'translator';
		$propertyMetadata1->relationship->type = PropertyRelationshipMetadata::ONE_HAS_MANY;
		$propertyMetadata1->relationship->entityMetadata = $this->entityMetadata;

		$propertyMetadata2 = Mockery::mock(PropertyMetadata::class);
		$propertyMetadata2->relationship = new PropertyRelationshipMetadata();
		$propertyMetadata2->relationship->entity = 'Tag';
		$propertyMetadata2->relationship->repository = 'TagsRepository';
		$propertyMetadata2->relationship->property = 'books';
		$propertyMetadata2->relationship->type = PropertyRelationshipMetadata::MANY_HAS_MANY;
		$propertyMetadata2->relationship->isMain = true;
		$propertyMetadata2->relationship->entityMetadata = $this->entityMetadata;

		// translated books
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('translatedBooks')->andReturn($propertyMetadata1);
		$this->model->shouldReceive('getRepository')->once()->with('BooksRepository')->andReturn($repository = Mockery::mock(IRepository::class));
		$repository->shouldReceive('getMapper')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);
		$this->conventions->shouldReceive('convertEntityToStorageKey')->once()->with('translator')->andReturn('translator_id');
		$this->conventions->shouldReceive('getStoragePrimaryKey')->once()->andReturn(['id']);
		$this->mapper->shouldReceive('getTableName')->once()->andReturn('books');

		// tags
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('tags')->andReturn($propertyMetadata2);
		$this->model->shouldReceive('getRepository')->once()->with('TagsRepository')->andReturn($repository = Mockery::mock(IRepository::class));
		$repository->shouldReceive('getMapper')->once()->andReturn($this->mapper);
		$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);
		$this->mapper->shouldReceive('getManyHasManyParameters')->once()->with($propertyMetadata2, $this->mapper)->andReturn(['books_x_tags', ['book_id', 'tag_id']]);
		$this->conventions->shouldReceive('getStoragePrimaryKey')->once()->andReturn(['id']);
		$this->mapper->shouldReceive('getTableName')->once()->andReturn('tags');

		// name
		$namePropertyMetadata = new PropertyMetadata();
		$namePropertyMetadata->name = 'name';
		$this->entityMetadata->shouldReceive('getProperty')->once()->with('name')->andReturn($namePropertyMetadata);
		$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);
		$this->conventions->shouldReceive('convertEntityToStorageKey')->once()->with('name')->andReturn('name');
		$this->conventions->shouldReceive('getStoragePrimaryKey')->twice()->andReturn(['id']);

		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('authors', '[books]', 'translatedBooks', '[authors.id] = [translatedBooks.translator_id]');
		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('translatedBooks', '[books_x_tags]', 'translatedBooks_books_x_tags', '[translatedBooks.id] = [translatedBooks_books_x_tags.book_id]');
		$this->queryBuilder->shouldReceive('leftJoin')->once()->with('translatedBooks_books_x_tags', '[tags]', 'translatedBooks_tags', '[translatedBooks_books_x_tags.tag_id] = [translatedBooks_tags.id]');
		$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('authors');
		$this->queryBuilder->shouldReceive('groupBy')->once()->with('%column[]', ['authors.id']);

		$columnReference = $this->builderHelper->processPropertyExpr($this->queryBuilder, 'translatedBooks->tags->name');
		Assert::same('translatedBooks_tags.name', $columnReference->args[1]);
	}


	public function testNotEntityProperty()
	{
		Assert::throws(function () {
			$this->mapper->shouldReceive('getRepository')->once()->andReturn($repository = Mockery::mock(IRepository::class));
			$repository->shouldReceive('getEntityMetadata')->once()->andReturns($this->entityMetadata);
			$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
			$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);

			$this->entityMetadata->shouldReceive('getProperty')->with('unknown')->andThrow(InvalidArgumentException::class);
			$this->builderHelper->processPropertyExpr($this->queryBuilder, 'unknown->test');
		}, InvalidArgumentException::class);
	}


	public function testNotEntityProperty2()
	{
		Assert::throws(function () {
			$this->mapper->shouldReceive('getRepository')->once()->andReturn($repository = Mockery::mock(IRepository::class));
			$repository->shouldReceive('getEntityMetadata')->once()->andReturns($this->entityMetadata);
			$this->queryBuilder->shouldReceive('getFromAlias')->once()->andReturn('books');
			$this->mapper->shouldReceive('getConventions')->once()->andReturn($this->conventions);

			$propertyMetadata = Mockery::mock(PropertyMetadata::class);
			$this->entityMetadata->shouldReceive('getClassName')->once()->andReturn('Entity');
			$this->entityMetadata->shouldReceive('getProperty')->with('name')->andReturn($propertyMetadata);

			$this->builderHelper->processPropertyExpr($this->queryBuilder, 'name->test');
		}, InvalidArgumentException::class);
	}
}


$conditionParserTest = new QueryBuilderHelperTest($dic);
$conditionParserTest->run();

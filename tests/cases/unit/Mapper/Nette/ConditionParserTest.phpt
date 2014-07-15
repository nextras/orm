<?php

namespace Nextras\Orm\Tests\Mapper\NetteDatabase;

use Mockery;
use Mockery\MockInterface;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\Nette\ConditionParser;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @testCase
 */
class ConditionParserTest extends TestCase
{
	/** @var ConditionParser */
	private $conditionParser;

	/** @var MockInterface */
	private $reflection;

	/** @var MockInterface */
	private $metadataStorage;

	/** @var MockInterface */
	private $model;

	/** @var MockInterface */
	private $mapper;

	/** @var MockInterface */
	private $entityMetadata;


	protected function setUp()
	{
		parent::setUp();

		$this->reflection = Mockery::mock('Nextras\Orm\StorageReflection\IDbStorageReflection');
		$this->model = Mockery::mock('Nextras\Orm\Model\IModel');
		$this->metadataStorage = Mockery::mock('Nextras\Orm\Model\MetadataStorage');
		$this->mapper = Mockery::mock('Nextras\Orm\Mapper\IMapper');
		$this->entityMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\EntityMetadata');

		$this->model->shouldReceive('getMetadataStorage')->andReturn($this->metadataStorage);
		$this->model->shouldReceive('getRepository')->with(mockery::any())->andReturn($this->model);
		$this->model->shouldReceive('getMapper')->andReturn($this->mapper);
		$this->mapper->shouldReceive('getStorageReflection')->andReturn($this->reflection);
		$this->mapper->shouldReceive('getRepository')->andReturn($this->mapper);
		$this->mapper->shouldReceive('getEntityClassNames')->andReturn(['any', 'any']);
		$this->metadataStorage->shouldReceive('get')->with(mockery::any())->andReturn($this->entityMetadata);

		$this->conditionParser = new ConditionParser($this->model, $this->mapper);
	}


	public function testHasOne()
	{
		$propertyMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata->relationshipRepository = 'AuthorsRepository';
		$propertyMetadata->relationshipType = PropertyMetadata::RELATIONSHIP_MANY_HAS_ONE;

		$this->entityMetadata->shouldReceive('hasProperty')->with('author')->andReturn(TRUE);
		$this->entityMetadata->shouldReceive('getProperty')->with('author')->andReturn($propertyMetadata);
		$this->entityMetadata->shouldReceive('getProperty')->with('name');

		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('author')->andReturn('author_id');
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('name')->andReturn('name');

		Assert::same('.author_id.name', $this->conditionParser->parse('this->author->name'));


		$this->entityMetadata->shouldReceive('hasProperty')->with('translator')->andReturn(TRUE);
		$this->entityMetadata->shouldReceive('getProperty')->with('translator')->andReturn($propertyMetadata);
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('translator')->andReturn('translator_id');

		Assert::same('.translator_id.name', $this->conditionParser->parse('this->translator->name'));
	}


	public function testOneHasMany()
	{
		$propertyMetadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata->relationshipRepository = 'BooksRepository';
		$propertyMetadata->relationshipProperty = 'author';
		$propertyMetadata->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY;

		$this->entityMetadata->shouldReceive('hasProperty')->with('books')->andReturn(TRUE);
		$this->entityMetadata->shouldReceive('getProperty')->with('books')->andReturn($propertyMetadata);
		$this->entityMetadata->shouldReceive('getProperty')->with('name');

		$this->reflection->shouldReceive('getStorageName')->andReturn('books');
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('author')->andReturn('author_id');
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('name')->andReturn('name');

		Assert::same(':books(author_id).name', $this->conditionParser->parse('this->books->name'));


		$propertyMetadata->relationshipProperty = 'translator';
		$this->entityMetadata->shouldReceive('hasProperty')->with('translatedBooks')->andReturn(TRUE);
		$this->entityMetadata->shouldReceive('getProperty')->with('translatedBooks')->andReturn($propertyMetadata);
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('translator')->andReturn('translator_id');

		Assert::same(':books(translator_id).name', $this->conditionParser->parse('this->translatedBooks->name'));
	}


	public function testManyHasMany()
	{
		$propertyMetadata1 = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata1->relationshipRepository = 'BooksRepository';
		$propertyMetadata1->relationshipProperty = 'translator';
		$propertyMetadata1->relationshipType = PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY;

		$propertyMetadata2 = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$propertyMetadata2->relationshipRepository = 'TagsRepository';
		$propertyMetadata2->relationshipProperty = 'books';
		$propertyMetadata2->relationshipType = PropertyMetadata::RELATIONSHIP_MANY_HAS_MANY;
		$propertyMetadata2->relationshipIsMain = TRUE;

		$this->entityMetadata->shouldReceive('hasProperty')->with('translatedBooks')->andReturn(TRUE);
		$this->entityMetadata->shouldReceive('getProperty')->with('translatedBooks')->andReturn($propertyMetadata1);
		$this->entityMetadata->shouldReceive('hasProperty')->with('tags')->andReturn(TRUE);
		$this->entityMetadata->shouldReceive('getProperty')->with('tags')->andReturn($propertyMetadata2);
		$this->entityMetadata->shouldReceive('getProperty')->with('name');

		$this->reflection->shouldReceive('getStorageName')->andReturn('books');
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('translator')->andReturn('translator_id');
		$this->reflection->shouldReceive('getManyHasManyStorageName')->with($this->mapper)->andReturn('books_x_tags');
		$this->reflection->shouldReceive('getManyHasManyStoragePrimaryKeys')->with($this->mapper)->andReturn(['book_id', 'tag_id']);
		$this->reflection->shouldReceive('convertEntityToStorageKey')->with('name')->andReturn('name');

		Assert::same(
			':books(translator_id):books_x_tags.tag_id.name',
			$this->conditionParser->parse('this->translatedBooks->tags->name')
		);
	}


	public function testNotEntityProperty()
	{
		Assert::throws(function() {
			$this->entityMetadata->shouldReceive('hasProperty')->with('unknown')->andReturn(FALSE);
			$this->entityMetadata->shouldReceive('getClassName')->andReturn('Entity');

			$this->conditionParser->parse('this->unknown->test');
		}, 'Nextras\Orm\InvalidArgumentException');

		Assert::throws(function() {
			$propertyMetadata = mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
			$this->entityMetadata->shouldReceive('hasProperty')->with('name')->andReturn(TRUE);
			$this->entityMetadata->shouldReceive('getProperty')->with('name')->andReturn($propertyMetadata);

			$this->conditionParser->parse('this->name->test');
		}, 'Nextras\Orm\InvalidArgumentException');
	}

}


$conditionParserTest = new ConditionParserTest($dic);
$conditionParserTest->run();

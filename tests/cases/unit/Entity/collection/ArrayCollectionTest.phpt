<?php

namespace Nextras\Orm\Tests\Entity\Collection;

use Mockery;
use Nextras\Orm\Entity\Collection\ArrayCollection;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @testCase
 */
class ArrayCollectionTest extends TestCase
{

	public function testFiltering()
	{
		/** @var ICollection $collection */
		list($collection, $authors, $books) = $this->createCollection();

		Assert::same($authors, iterator_to_array($collection));

		Assert::same([$authors[1]], iterator_to_array($collection->findBy(['this->name' => 'Sansa'])));
		Assert::same([$authors[1]], iterator_to_array($collection->findBy(['this->books->title' => 'Valyria 2'])));
		Assert::same([$authors[0]], iterator_to_array($collection->findBy(['this->books->title' => 'Valyria 1'])));
		Assert::same([$authors[0]], iterator_to_array($collection->findBy(['this->books->title' => 'The Wall'])));

		// IN operator
		Assert::same(
			[$authors[0], $authors[1]],
			iterator_to_array($collection->findBy(['this->books->title' => ['The Wall', 'Valyria 2']]))
		);
	}


	public function testSorting()
	{
		/** @var ICollection $collection */
		list($collection, $authors, $books) = $this->createCollection();

		Assert::same(
			[$authors[2], $authors[0], $authors[1]],
			iterator_to_array($collection->orderBy('this->name'))
		);
		Assert::same(
			[$authors[1], $authors[0], $authors[2]],
			iterator_to_array($collection->orderBy('this->name', ICollection::DESC))
		);
		Assert::same(
			[$authors[1], $authors[2], $authors[0]],
			iterator_to_array($collection->orderBy('this->age', ICollection::DESC))
		);
	}


	public function testSlicing()
	{
		/** @var ICollection $collection */
		list($collection, $authors, $books) = $this->createCollection();

		Assert::same($authors, iterator_to_array($collection->limitBy(3)));
		Assert::same([$authors[0]], iterator_to_array($collection->limitBy(1)));
		Assert::same([$authors[1]], iterator_to_array($collection->limitBy(1, 1)));
		Assert::same([$authors[1], $authors[2]], iterator_to_array($collection->limitBy(2, 1)));
		Assert::same([], iterator_to_array($collection->limitBy(2, 3)));
	}


	public function testTogether()
	{
		/** @var ICollection $collection */
		list($collection, $authors, $books) = $this->createCollection();

		Assert::same(
			[$authors[0]],
			iterator_to_array($collection
				->findBy(['this->books->title' => ['Valyria 1', 'Valyria 2']])
				->orderBy('age')
				->limitBy(1))
		);

		Assert::same(
			[$authors[1]],
			iterator_to_array($collection
				->findBy(['this->books->title' => ['Valyria 1', 'Valyria 2']])
				->orderBy('age')
				->limitBy(2, 1))
		);
	}


	public function testCount()
	{
		/** @var ICollection $collection */
		list($collection, $authors, $books) = $this->createCollection();

		Assert::same(
			1,
			count($collection
				->findBy(['this->books->title' => ['Valyria 1', 'Valyria 2']])
				->orderBy('age')
				->limitBy(2, 1))
		);
	}


	private function createCollection()
	{
		$authors = [
			$this->e('Nextras\Orm\Tests\Author', ['name' => 'Jon', 'born' => '2012-01-01']),
			$this->e('Nextras\Orm\Tests\Author', ['name' => 'Sansa', 'born' => '2010-01-01']),
			$this->e('Nextras\Orm\Tests\Author', ['name' => 'Eddard', 'born' => '2011-01-01']),
		];

		$books = [
			$this->e('Nextras\Orm\Tests\Book', ['title' => 'The Wall', 'author' => $authors[0]]),
			$this->e('Nextras\Orm\Tests\Book', ['title' => 'Valyria 1', 'author' => $authors[0]]),
			$this->e('Nextras\Orm\Tests\Book', ['title' => 'Valyria 2', 'author' => $authors[1]]),
			$this->e('Nextras\Orm\Tests\Book', ['title' => 'Valyria 3', 'author' => $authors[2]]),
		];

		return [new ArrayCollection($authors), $authors, $books];
	}

}


$test = new ArrayCollectionTest($dic);
$test->run();

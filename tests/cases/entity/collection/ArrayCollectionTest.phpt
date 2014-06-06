<?php

namespace Nextras\Orm\Tests\Entity\Collection;

use Mockery;
use Nextras\Orm\Entity\Collection\ArrayCollection;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap-model.php';


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


	private function createCollection()
	{
		global $model;

		$books = [];
		$books[0] = new Book();
		$books[0]->title = 'The Wall';
		$books[1] = new Book();
		$books[1]->title = 'Valyria 1';
		$books[2] = new Book();
		$books[2]->title = 'Valyria 2';
		$books[3] = new Book();
		$books[3]->title = 'Valyria 3';

		foreach ($books as $book) { $model->books->attach($book); }

		$authors = [];
		$authors[0] = new Author();
		$authors[0]->name = 'Jon';
		$authors[0]->born = '2012-01-01';
		$authors[0]->books->set([$books[0], $books[1]]);
		$authors[1] = new Author();
		$authors[1]->name = 'Sansa';
		$authors[1]->born = '2010-01-01';
		$authors[1]->books->add($books[2]);
		$authors[2] = new Author();
		$authors[2]->name = 'Eddard';
		$authors[2]->born = '2011-01-01';
		$authors[2]->books->add($books[3]);

		return [new ArrayCollection($authors), $authors, $books];
	}

}


$test = new ArrayCollectionTest;
$test->run();

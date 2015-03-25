<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryMagicMethodsTest extends DataTestCase
{

	public function testMagicGetByFindByMethods()
	{
		$book = $this->orm->books->getByTitle('Book 1');
		Assert::same(1, $book->id);

		$book = $this->orm->books->getByTitle('Book 10');
		Assert::null($book);

		Assert::throws(function() {
			$this->orm->books->findByTitle('Book 1');
		}, 'Nette\MemberAccessException');
	}


	public function testDefinedProxyMethods()
	{
		$books = $this->orm->books->findBooksWithEvenId();
		Assert::type('Nextras\Orm\Collection\ICollection', $books);
		Assert::same(2, $books->count());
	}

}


$test = new RepositoryMagicMethodsTest($dic);
$test->run();

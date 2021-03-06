<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Orm\Exception\NoResultException;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryTest extends DataTestCase
{
	public function testNonNullable(): void
	{
		Assert::throws(function (): void {
			$this->orm->books->findAll()->getByIdChecked(923);
		}, NoResultException::class);

		Assert::throws(function (): void {
			$this->orm->books->findAll()->getByChecked(['id' => 923]);
		}, NoResultException::class);

		Assert::type(Book::class, $this->orm->books->findAll()->getByIdChecked(1));
		Assert::type(Book::class, $this->orm->books->findAll()->getByChecked(['id' => 1]));
	}
}


$test = new RepositoryTest();
$test->run();
